<?php
namespace AkSearch\Controller;

use VuFind\Controller\AbstractBase;
use \ZfcRbac\Service\AuthorizationServiceAwareInterface;
use \ZfcRbac\Service\AuthorizationServiceAwareTrait;
use \Zend\Http\Response as HttpResponse;
use Zend\Crypt\Password\Bcrypt;
use Zend\Mail as Mail;


// Hide all PHP errors and warnings as this could brake the JSON and/or XML output
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

class ApiController extends AbstractBase implements AuthorizationServiceAwareInterface {
	
	use AuthorizationServiceAwareTrait;
	
	protected $host;
	protected $database;
	protected $config;
	protected $configAKsearch;
	protected $configAlma;
	private $auth;
	
	/**
	 * User database table
	 * @var \AkSearch\Db\Table\User
	 */
	protected $userTable;
	
	/**
	 * Http service
	 * @var \VuFindHttp\HttpService
	 */
	protected $httpService;
	
	// Response to external
	protected $httpResponse;
	protected $httpHeaders;
	
	
	/**
	 * Constructor
	 */
	public function __construct(\VuFind\Config\PluginManager $configLoader, \VuFind\Db\Table\PluginManager $dbTableManager, \VuFindHttp\HttpService $httpService) {
		$configAleph = $configLoader->get('Aleph');
		$this->config = $configLoader->get('config');
		$this->configAKsearch = $configLoader->get('AKsearch');
		$this->configAlma = $configLoader->get('Alma');
		
		date_default_timezone_set($this->config->Site->timezone);
		$this->host = rtrim(trim($configAleph->Catalog->host),'/');
		$this->database = $configAleph->Catalog->useradm;
		
		$this->httpResponse = new HttpResponse();
		$this->httpHeaders = $this->httpResponse->getHeaders();

		// Initialize the authorization service (for permissions)
		$init = new \ZfcRbac\Initializer\AuthorizationServiceInitializer();
		$init->initialize($this, $configLoader);
		$this->auth = $this->getAuthorizationService();
		if (!$this->auth) {
			throw new \Exception('Authorization service missing');
		}
		
		$this->userTable = $dbTableManager->get('user');
		$this->httpService = $httpService;
	}
	
	
	public function webhookAction() {
		// Check if API is activated and permission is granted. If not, return the response that is already set in checkApi();
		if (!$this->checkApi('webhook', 'webhookPermission')) {
			return $this->httpResponse;
		}
		
		// Request from external
		$request = $this->getRequest();
		
		// Get request method (GET, POST, ...)
		$requestMethod = $request->getMethod();
		
		// Get request body if method is POST and is not empty
		$requestBodyJson = ($request->getContent() != null && !empty($request->getContent()) && $requestMethod == 'POST') ? json_decode($request->getContent()) : null;
		//$requestBodyArray = ($request->getContent() != null && !empty($request->getContent()) && $requestMethod == 'POST') ? json_decode($request->getContent(), true) : null;
		
		// Get webhook action
		$webhookAction = (isset($requestBodyJson->action)) ? $requestBodyJson->action: null;
		
		// Perform user-api action
		switch ($webhookAction) {
			case 'USER':
				return $this->webhookUser($requestBodyJson);
			case 'NOTIFICATION':
				return $this->webhookNotification();
			case 'JOB_END':
				return $this->webhookJobEnd();
			default:
				return $this->webhookChallenge();
				break;
		}
	}
	
	
	private function webhookUser($requestBodyJson) {
				
		// Get webhook secret from Alma.ini. If it is not set, return an error message
		$almaWebhookSecret = (isset($this->configAlma->Webhook->secret) && !empty($this->configAlma->Webhook->secret)) ? $this->configAlma->Webhook->secret : null;
		if ($almaWebhookSecret == null) {
			$errorText = 'Please provide webhook secret in Alma.ini in section [Webhook]. It must be the same value that is used in Alma in the integration profile for the webhook!';
			$returnArray['error'] = $errorText;
			$returnJson = json_encode($returnArray,  JSON_PRETTY_PRINT);
			$this->httpHeaders->addHeaderLine('Content-type', 'application/json');
			$this->httpResponse->setStatusCode(401); // Set HTTP status code to Unauthorized (401)
			$this->httpResponse->setContent($returnJson);
			error_log('[Alma] '.$errorText); // Log the error in our own system
			return $this->httpResponse;
		}
				
		// Calculate hmac-sha256 hash from request body we get from Alma webhook and sign it with the Alma webhook secret from Alma.ini
		$requestBodyString = json_encode($requestBodyJson, JSON_UNESCAPED_UNICODE); // We have to use JSON_UNESCAPED_UNICODE!
		$hashedHmacMessage = base64_encode(hash_hmac('sha256', $requestBodyString, $almaWebhookSecret, true));
		
		// Get hashed message signature from request header of Alma webhook request
		$almaSignature = ($this->getRequest()->getHeaders()->get('X-Exl-Signature')) ? $this->getRequest()->getHeaders()->get('X-Exl-Signature')->getFieldValue() : null;
		
		// Check for correct signature and return error message if check fails
		if ($almaSignature == null || $almaSignature != $hashedHmacMessage) {
			$returnArray['error'] = 'Unauthorized: Signature value not correct!';
			$returnJson = json_encode($returnArray,  JSON_PRETTY_PRINT);
			$this->httpHeaders->addHeaderLine('Content-type', 'application/json');
			$this->httpResponse->setStatusCode(401); // Set HTTP status code to Unauthorized (401)
			$this->httpResponse->setContent($returnJson);
			error_log('[Alma] Unauthorized: Signature value not correct!'); // Log the error in our own system
			return $this->httpResponse;
		}
		
		// Set some default values that will be overwritten if appropriate
		$password = null;
		$passHash = null;
		$createIfNotExist = false;
		$isOtp = 0;
		
		// Get method from webhook (e. g. "create" for "new user")
		$method = (isset($requestBodyJson->webhook_user->method)) ? $requestBodyJson->webhook_user->method : null;
		
		// Get primary ID
		$primaryId = (isset($requestBodyJson->webhook_user->user->primary_id)) ? $requestBodyJson->webhook_user->user->primary_id : null;
		
		// Get barcode
		$barcode = null;
		$userIdentifiers = (isset($requestBodyJson->webhook_user->user->user_identifier)) ? $requestBodyJson->webhook_user->user->user_identifier : null;
		foreach ($userIdentifiers as $userIdentifier) {
			$idType = (isset($userIdentifier->id_type->value)) ? $userIdentifier->id_type->value : null;
			if ($idType != null && $idType == 'BARCODE' && $barcode == null) {
				$barcode = (isset($userIdentifier->value)) ? $userIdentifier->value : null;
			}
		}
		
		// Get first name
		$firstName = (isset($requestBodyJson->webhook_user->user->first_name)) ? $requestBodyJson->webhook_user->user->first_name : null;
		
		// Get last name
		$lastName = (isset($requestBodyJson->webhook_user->user->last_name)) ? $requestBodyJson->webhook_user->user->last_name : null;
		
		// Get preferred eMail
		$eMail = null;
		$contactEmails = (isset($requestBodyJson->webhook_user->user->contact_info->email)) ? $requestBodyJson->webhook_user->user->contact_info->email : null;
		foreach ($contactEmails as $contactEmail) {
			$preferred = (isset($contactEmail->preferred)) ? $contactEmail->preferred : false;
			if ($preferred && $eMail == null) {
				$eMail = (isset($contactEmail->email_address)) ? $contactEmail->email_address : null;
			}
		}

		// Do stuff if a new user is create via Alma webhook.
		if ($method == 'CREATE') {
			
			// Set variable for getByCatalogId() function
			$createIfNotExist = true;
			
			// Create new barcode
			if ($barcode == null) {
				$stringForHash = ($almaSignature != null) ? $almaSignature . time() : $firstName . $lastName . $eMail . time(); // Message Signature or Name and eMail + Timestamp
				$barcode = $this->akSearch()->generateBarcode($stringForHash);
				
				// Write barcode back to Alma
				$addedBarcodeStatus = $this->barcodeToAlma($primaryId, $barcode);
				
				// Return error if barcode could not be written to Alma
				if ($addedBarcodeStatus != '200') {
					$errorText = 'Problem writing automatically generated barcode from VuFind back to Alma User with primary ID '.$primaryId.' while creating new user in VuFind from Alma webhook! Http status code: '.$addedBarcodeStatus;
					$returnArray['error'] = $errorText;
					$returnJson = json_encode($returnArray,  JSON_PRETTY_PRINT);
					$this->httpHeaders->addHeaderLine('Content-type', 'application/json');
					$this->httpResponse->setStatusCode($addedBarcodeStatus); // Set HTTP status code according to Alma return value
					$this->httpResponse->setContent($returnJson);
					error_log('[Alma] '.$errorText); // Log the error in our own system
					return $this->httpResponse;
				}
			}
			
			// Generate one-time-password
			$password = $this->generatePassword();
			/*
			//if ($this->passwordHashingEnabled()) {
			if ($this->akSearch()->passwordHashingEnabled()) { // See Controller\Plugin\AkSearch.php
				$bcrypt = new Bcrypt();
				$passHash = $bcrypt->create($password);
			}
			*/
			$isOtp = 1;
			
			// Send eMail to user with one-time-password and barcode (= username):
			$isMailSent = $this->sendMail($barcode, $password, $eMail);
			if (!$isMailSent) {
				$errorText = 'Could not send eMail to user with Alma '.$primaryId.'. User was not added to VuFind!';
				$returnArray['error'] = $errorText;
				$returnJson = json_encode($returnArray,  JSON_PRETTY_PRINT);
				$this->httpHeaders->addHeaderLine('Content-type', 'application/json');
				$this->httpResponse->setStatusCode(500); // Set HTTP status code to Internal Server Error (500)
				$this->httpResponse->setContent($returnJson);
				error_log('[Alma] '.$errorText); // Log the error in our own system
				return $this->httpResponse;
			}
		}
		
		
		$user = $this->akSearch()->createOrUpdateUserInDb($firstName, $lastName, $eMail, $password, $isOtp, $primaryId, $barcode, $createIfNotExist);
		if ($user != null) {
			$this->httpResponse->setStatusCode(200); // Set HTTP status code to OK (200)
		} else {
			// Create a return message in case of error
			$errorText = 'Error updating user in VuFind from Alma webhook: User with Alma primary ID '.$primaryId.' was not found in VuFind user table by cat_id value '.$primaryId;
			$returnArray['error'] = $errorText;
			$returnJson = json_encode($returnArray,  JSON_PRETTY_PRINT);
			$this->httpHeaders->addHeaderLine('Content-type', 'application/json');
			$this->httpResponse->setStatusCode(404); // Set HTTP status code to Not Found (404)
			$this->httpResponse->setContent($returnJson);
			error_log('[Alma] '.$errorText); // Log the error in our own system
		}
		/*
		// Update or create user in database table
		$user = ($primaryId != null) ? $this->userTable->getByCatalogId($primaryId, $barcode, $createIfNotExist) : null;
		if ($user != null && !empty($user)) {
			$user->cat_id = $primaryId;
			$user->username = $barcode;
			$user->firstname = $firstName;
			$user->lastname = $lastName;
			$user->email = $eMail;
			$user->cat_username = $barcode;
			if ($createIfNotExist && $passHash != null) {
				$user->pass_hash = $passHash;
			} else if ($createIfNotExist && $password != null){
				$user->password = $password;
			}
			$user->is_otp = $isOtp;
			$user->save(); // Update user record
			$this->httpResponse->setStatusCode(200); // Set HTTP status code to OK (200)
		} else {
			// Create a return message in case of error
			$errorText = 'Error updating user in VuFind from Alma webhook: User with Alma primary ID '.$primaryId.' was not found in VuFind user table by cat_id value '.$primaryId;
			$returnArray['error'] = $errorText;			
			$returnJson = json_encode($returnArray,  JSON_PRETTY_PRINT);
			$this->httpHeaders->addHeaderLine('Content-type', 'application/json');
			$this->httpResponse->setStatusCode(404); // Set HTTP status code to Not Found (404)
			$this->httpResponse->setContent($returnJson);
			error_log('[Alma] '.$errorText); // Log the error in our own system
		}
		*/
		
		return $this->httpResponse;
	}
	
	
	private function webhookJobEnd() {
		$this->httpHeaders->addHeaderLine('Content-type', 'text/plain');
		$this->httpResponse->setStatusCode(501); // Set HTTP status code to Not Implemented (501)
		$this->httpResponse->setContent('JOB_END webhook not implemented yet.');
		return $this->httpResponse;
	}
	
	
	private function webhookNotification() {
		$this->httpHeaders->addHeaderLine('Content-type', 'text/plain');
		$this->httpResponse->setStatusCode(501); // Set HTTP status code to Not Implemented (501)
		$this->httpResponse->setContent('NOTIFICATION webhook not implemented yet.');
		return $this->httpResponse;
	}
	
	
	private function webhookChallenge($returnFormat = 'json') {

		// Get challenge string from the get parameter that Alma sends us. We need to return this string in the return message.
		$secret = $this->params()->fromQuery('challenge');
		
		// Create the return array
		$returnArray['challenge'] = $secret; // Secret from Alma.ini - according to format described at https://developers.exlibrisgroup.com/alma/integrations/webhooks
		$returnArray = array_filter($returnArray); // Remove null from array
		
		// Create return json value
		$returnJson = json_encode($returnArray,  JSON_PRETTY_PRINT);
		
		// Set HTTP status code to OK (200)
		$this->httpResponse->setStatusCode(200);
		
		// Return XML
		if ($returnFormat == 'xml') {
			$this->httpHeaders->addHeaderLine('Content-type', 'text/plain');
			$this->httpResponse->setContent('Only "json" response is supported at the moment!');
		} else if ($returnFormat == 'json') {
			// Return JSON (Default)
			$this->httpHeaders->addHeaderLine('Content-type', 'application/json');
			$this->httpResponse->setContent($returnJson);
		} else {
			$this->httpResponse->setContent('You have to define a valid response format! Only "json" is supported at the moment!');
		}
		
		return $this->httpResponse;
	}
	
	
	public function userAction() {
		
		// Check if API is activated and permission is granted. If not, return the response that is already set in checkApi();
		if (!$this->checkApi('user', 'userPermission')) {
			return $this->httpResponse;
		}
		
		// Which user-api action should we perform (last part of URL) - configured in module.config.php
		$apiUserAction = $this->params()->fromRoute('apiUserAction');
		
		// Request from external
		$request = $this->getRequest();
		
		// Get request method (GET, POST, ...)
		$requestMethod = $request->getMethod();
		
		// Get request body
		$requestBodyArray = ($request->getContent() != null && !empty($request->getContent()) && $requestMethod == 'POST') ? json_decode($request->getContent(), true) : null;
		
		// Perform user-api action
		switch ($apiUserAction) {
			case 'Auth':
				$username = $requestBodyArray['username'];
				$password = $requestBodyArray['password'];
				return $this->userAuth($requestMethod, $this->host, $this->database, $username, $password);
				break;
			default:
				$this->httpHeaders->addHeaderLine('Content-type', 'text/plain');
				$this->httpResponse->setContent('API is working. No action defined with this request.');
				$this->httpResponse->setStatusCode(200); // Set HTTP status code to OK (200)
				return $this->httpResponse;
		}
	}
	

	private function userAuth($requestMethod, $host, $library, $username, $password, $returnFormat = 'json') {
		// Only POST requests are allowed
		if ($requestMethod != 'POST') {
			$this->httpHeaders->addHeaderLine('Content-type', 'text/plain');
			$this->httpResponse->setContent('Only POST requests are allowed.');
			$this->httpResponse->setStatusCode(405); // Set HTTP status code to Method Not Allowed (405)
			return $this->httpResponse; // Stop code execution here if request method is not POST
		}
		
		// Default values for return array
		$isValid = 'U'; // U = Unknown (Default) - we don't know yet if the user credentials are valid or not
		$userExists = 'U'; // U = Unknown (Default) - we don't know yet if the user credentials really exists or not
		$isExpired = 'U'; // U = Unknown. At this point we don't know if the user account is expired
		$expired = null; // null. We use null because we can easily remove null values from the return array. Data for account expiration should only be shown when the account really is expired.
		$expireDateTS = null;
		$expiryDateFormatted = null;
		$isBlocked = 'U'; // U = Unknown. At this point we don't know if the user has blocks or not
		$blocks = null; // null. We use null because we can easily remove null values from the return array. Blocks should only be shown when there really are blocks.
		$hasError = null; // null = There is no error. We use null because we can easily remove null values from the return array. Errors should only be shown when there really are errors.
		$errorMsg = null; // null = There is no error message.
		
		// Return variables
		$returnFormat = strtolower($returnFormat);
		$returnArray = [];
		$returnJson = null;
		
		// Creating params for call to ILS API
		$params = [
				'op' 			=> 'bor-auth',
				'library'		=> $library,
				'bor_id'		=> $username,
				'verification'	=> $password
		];
		
		// Geting result as SimpleXMLElement
		$xResult = $this->callX($host, $params);
		$userXml = new \SimpleXMLElement($xResult);

		// Errors from API call
		$errorMsg = trim((string) $userXml->{'error'});
		$errorMsg = ($errorMsg != null && !empty($errorMsg)) ? $errorMsg : null;
				
		// Check for error
		if ($errorMsg) {
			$isValid = 'N'; // N = No. User is not valid			
			$hasError = 'Y'; // Y = Yes. There is an error
			$this->httpResponse->setStatusCode(500); // Default HTTP return status code: Internal Server Error (500)
			$this->httpResponse->setContent($errorMsg);
			error_log('[API] ApiController -> userAuth(): '.$errorMsg);
			
			// Check if user exists or not and set HTTP status code for return headers
			if ($errorMsg == 'Error in Verification') {
				$userExists = 'N'; // Set from default "U" to "N" as 'Error in Verification' tells us that the user credentials does not exist
				$this->httpResponse->setStatusCode(401);
			} else if ($errorMsg == 'Both Bor_Id and Verification must be supplied') {
				$this->httpResponse->setStatusCode(401); // At least one part of the user credentials was not supplied
			}
		} else {
			
			$userExists= 'Y'; // Y = Yes. The user credentials exists.
			$isExpired = 'N'; // Default
			$isBlocked = 'N'; // Default			
			
			// Check if user account is expired
			$expiryDate = (string) $userXml->{'z305'}->{'z305-expiry-date'};
			$expiryDateObj = \DateTime::createFromFormat('d/m/Y H:i:s', $expiryDate.' 23:59:59');
			$expireDateTS = $expiryDateObj->getTimestamp();
			$nowTs = time();
			if ($expireDateTS < $nowTs) {
				$isExpired = 'Y';
				$expiryDateFormatted = date('d.m.Y', $expireDateTS);
				$expired['timestamp'] = $expireDateTS;
				$expired['formatted'] = $expiryDateFormatted;
			}
						
			// Blocks in masterdata (Stammdaten)
			for ($i = 1; $i <= 3; $i++) { // There are max. 3 blocks
				${'blockSD'.$i} = (string) $userXml->{'z303'}->{'z303-delinq-'.$i};
				${'blockNoteSD'.$i} = (string) $userXml->{'z303'}->{'z303-delinq-n-'.$i};
				
				if (${'blockSD'.$i} != '00') {
					$blocks[$i]['code'] = ${'blockSD'.$i};
					$blocks[$i]['note'] = ${'blockNoteSD'.$i};
				}
			}
			
			// Blocks in sublibrary data (Zweigstellenrechte)
			for ($i = 1; $i <= 3; $i++) { // There are max. 3 blocks, but we need another counter so that we won't override the blocks from master data ("Stammdaten")
				$counter = 4; // Counter for not overriding existing array keys from master data (Stammdaten) blocks
				${'blockZR'.$i} = (string) $userXml->{'z305'}->{'z305-delinq-'.$i};
				${'blockNoteZR'.$i} = (string) $userXml->{'z305'}->{'z305-delinq-n-'.$i};
				
				if (${'blockZR'.$i} != '00') {
					$blocks[$counter]['code'] = ${'blockZR'.$i};
					$blocks[$counter]['note'] = ${'blockNoteZR'.$i};
				}
				$counter++;
			}
			
			// Actually check if there are blocks
			if ($blocks != null && !empty($blocks)) {
				$isBlocked = 'Y';
				
				// Remove null from array
				$blocks = array_filter($blocks);
				
				// Reset numerical array keys
				$blocks = $this->fixArrayKeys($blocks);
			}
			
			// Check if user is valid
			if ($isExpired == 'N' && $isBlocked == 'N') {
				$isValid = 'Y'; // The user is valid (not expired and no blocks) and is allowed to access the resource
				$this->httpResponse->setStatusCode(200); // Set HTTP status code to OK (200)
			} else {
				$isValid = 'N'; // The user is NOT valid (expired or has blocks) and is NOTallowed to access the resource
				$this->httpResponse->setStatusCode(403); // Set HTTP status code to Forbidden (403)
			}
		}
		
		// Create the return array
		$returnArray['user']['isValid'] = $isValid;
		$returnArray['user']['exists'] = $userExists;
		$returnArray['expired']['isExpired'] = $isExpired;
		if ($expired) { $returnArray['expired']['date'] = $expired; }
		$returnArray['blocks']['isBlocked'] = $isBlocked;
		if ($blocks) { $returnArray['blocks']['reasons'] = $blocks; }
		if ($hasError) { $returnArray['request']['hasError'] = $hasError; }
		if ($errorMsg) { $returnArray['request']['errorMsg'] = $errorMsg; } // The error message from the ILS server
		$returnArray= array_filter($returnArray); // Remove null from array
		$returnJson = json_encode($returnArray,  JSON_PRETTY_PRINT);
		
		// Return XML
		if ($returnFormat == 'xml') {
			$this->httpHeaders->addHeaderLine('Content-type', 'text/plain');
			$this->httpResponse->setContent('Only "json" response is supported at the moment!');
			// $headers->addHeaderLine('Content-type', 'text/xml');
			// $this->response->setContent($XML);
		} else if ($returnFormat == 'json') {
			// Return JSON (Default)
			$this->httpHeaders->addHeaderLine('Content-type', 'application/json');
			$this->httpResponse->setContent($returnJson);
		} else {
			$this->httpResponse->setContent('You have to define a valid response format! Only "json" is supported at the moment!');
		}
		
		return $this->httpResponse;
	}
	
	
	private function callX($host, $params = null, $https = true, $method = 'GET', $data = false) {
		// Create URL for the curl call
		$paramList = (!empty($params) && $params != null) ? http_build_query($params) : [];
		$baseXUrl = ($https) ? 'https://'.$host.'/X' : 'http://'.$host.'/X';
		$url = sprintf('%s?%s', $baseXUrl, $paramList);
		
		error_log('[API] ApiController -> callX(). Calling URL '.$url.' with method '.$method);
		
		// Initialize curl
		$curl = curl_init();
		
		switch ($method) {
			case 'GET' :
				curl_setopt($curl, CURLOPT_HTTPGET, 1);
				break;
			case 'POST' :
				curl_setopt($curl, CURLOPT_POST, 1);
				if ($data) {
					curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
				}
				break;
			case 'PUT' :
				curl_setopt($curl, CURLOPT_PUT, 1);
				break;
			default :
				
		}
		
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		
		$result = curl_exec($curl);
		
		curl_close($curl);
				
		return $result;
	}
	
	
	/**
	 * Perform HTTP request.
	 *
	 * @param string $url    URL of request
	 * @param string $method HTTP method
	 *
	 * @return array	xml => SimpleXMLElement, status => HTTP status code
	 */
	/*
	private function doHTTPRequest($url, $method = 'GET', $rawBody = null, $headers = null) {
		if ($this->debug_enabled) {
			$this->debug("URL: '$url'");
		}
		
		$result = null;
		$statusCode = null;
		$returnArray = null;
		
		try {
			
			$client = $this->httpService->createClient($url);
			$client->setMethod($method);
			
			
			if (isset($rawBody)) {
				$client->setRawBody($rawBody);
			}
			
			if (isset($headers)) {
				$client->setHeaders($headers);
			}
			
			$result = $client->send();
			$statusCode = $result->getStatusCode();
		} catch (\Exception $e) {
			throw new ILSException($e->getMessage());
		}
		
		if ($result->isServerError()) {
			throw new ILSException('HTTP error: '.$statusCode);
		}
		
		$answer = $result->getBody();
		if ($this->debug_enabled) {
			$this->debug("url: $url response: $answer (HTTP status code: $statusCode)");
		}
		
		$answer = str_replace('xmlns=', 'ns=', $answer);
		$xml = simplexml_load_string($answer);
		
		if (!$xml && $result->isServerError()) {
			if ($this->debug_enabled) {
				$this->debug("XML is not valid or HTTP error, URL: $url, HTTP status code: $statusCode");
			}
			throw new ILSException("XML is not valid or HTTP error, URL: $url method: $method answer: $answer, HTTP status code: $statusCode.");
		}
		
		$returnArray = ['xml' => $xml, 'status' => $statusCode];
		
		return $returnArray;
	}
	*/
	
	
	private function fixArrayKeys($array) {
		$checkNumber = false;
		foreach ($array as $key => $value) {
			if (is_array($value)) {
				$array[$key] = $this->fixArrayKeys($value);
			}
			if (is_numeric($key)) {
				$checkNumber = true;
			}
		}
		if ($checkNumber === true) {
			return array_values($array);
		} else {
			return $array;
		}
	}
	
	
	private function checkApi($apiName, $apiPermissionName) {
		$returnValue = false;
		// Check activation of user API and it's access permission
		if (!$this->isApiActivated($apiName)) { // Check if user API is activated in AKsearch.ini (user = true)
			$this->httpHeaders->addHeaderLine('Content-type', 'text/plain');
			$this->httpResponse->setContent('API "'.$apiName.'" is not activated in AKsearch.ini');
			$this->httpResponse->setStatusCode(403); // Set HTTP status code to Forbidden (403)
		} else { // If API is activated, check if the permission is granted (using "userPermission" in AKsearch.ini and the settings in permissions.ini)
			$permissionIsGranted = $this->isApiAccessAllowed($apiPermissionName);
			if (!$permissionIsGranted) {
				$this->httpHeaders->addHeaderLine('Content-type', 'text/plain');
				$this->httpResponse->setContent('Access to API is not allowed.');
				$this->httpResponse->setStatusCode(403); // Set HTTP status code to Forbidden (403)
			} else {
				// If everything is OK, return true
				$returnValue = true;
			}
		}
		
		return $returnValue;
	}
	
	
	private function isApiActivated($apiName) {
		return (isset($this->configAKsearch->API->$apiName)) ? filter_var($this->configAKsearch->API->$apiName, FILTER_VALIDATE_BOOLEAN) : false;
	}
	
	
	private function isApiAccessAllowed($apiPermissionName) {
		$permission = $this->configAKsearch->API->$apiPermissionName;
		$permissionIsGranted = false; // Default
		
		if (isset($permission) && $permission != 'ALL') {
			$permissionIsGranted = $this->auth->isGranted($permission); // Check if permission is granted
		} else if ($permission == 'ALL') {
			// Permission is set to "ALL". Every IP-Adress can access the API. We do not check them with the authentication service.
			$permissionIsGranted = true;
		}
		
		return $permissionIsGranted;
	}
	
	
	/**
	 * Is password hashing enabled?
	 *
	 * @return bool
	 */
	/*
	private function passwordHashingEnabled() {
		return isset($this->config->Authentication->hash_passwords) ? $this->config->Authentication->hash_passwords : false;
	}
	*/
	
	
	private function generatePassword() {
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ23456789$&#*+!%+-_@?';
		$shuffledChars = str_shuffle($chars);
		
		$length = strlen($chars) - 1;
		$password = '';
		for ($i = 0; $i < 12; $i++) {
			$position = mt_rand(0, $length);
			$password .= $chars[$position];
		}
		
		return $password;
	}
	
	
	private function barcodeToAlma($primaryId, $barcodeValue) {
		$apiUrl = $this->configAlma->API->url;
		$apiKey = $this->configAlma->API->key;
				
		// Get the Alma user XML object from the Alma API
		$almaUserObject = $this->akSearch()->doHTTPRequest($apiUrl.'users/'.$primaryId.'?&apikey='.$apiKey, 'GET');
		$almaUserObject = $almaUserObject['xml']; // Get the user XML object from the return-array.
		
		// Remove user roles (they are not touched)
		unset($almaUserObject->user_roles);
		
		// Set new barcode to XML
		$userIds = $almaUserObject->user_identifiers;
		$newBarcode = $userIds->addChild('user_identifier');
		$newBarcode->addAttribute('segment_type', 'Internal');
		$newBarcode->addChild('id_type', 'BARCODE')->addAttribute('desc', 'Barcode');
		$newBarcode->addChild('value', $barcodeValue);
		$newBarcode->addChild('status', 'ACTIVE');
		
		// Get XML for update process via API
		$almaUserObjectForUpdate = $almaUserObject->asXML();
		
		// Send update via HTTP PUT
		$updateResult = $this->akSearch()->doHTTPRequest($apiUrl.'users/'.$primaryId.'?user_id_type=all_unique&apikey='.$apiKey, 'PUT', $almaUserObjectForUpdate, ['Content-type' => 'application/xml']);
		
		return $updateResult['status']; // Return http status code
	}
	
	
	private function sendMail($username, $password, $to) {
		$success = false;
		
		$email_subject = 'AK Bibliothek Wien - Ihr Account';
		$email_message = 'URL: http://aksearch.localhost.at/AkSites/SetPasswordWithOtp' . "\n";
		$email_message = 'Username: ' . $username. "\n";
		$email_message .= 'Password: ' . $password. "\n";
		
		$from = (isset($this->configAlma->Webhook->emailFrom)) ? $this->configAlma->Webhook->emailFrom : null;
		$bcc = (isset($this->configAlma->Webhook->emailBCC)) ? $this->configAlma->Webhook->emailBCC: null;
		
		if ($from != null) {
			// This sets up the email to be sent
			$mail = new Mail\Message();
			$mail->setBody($email_message);
			$mail->setFrom($from);
			$mail->addTo($to);
			$mail->setSubject($email_subject);
			$mail->addBcc($bcc);
			try {
				$this->getServiceLocator()->get('VuFind\Mailer')->getTransport()->send($mail);
				$success = true;
			} catch (\VuFind\Exception\Mail $mex) {
				error_log('[Alma] '.$mex->getMessage(). '. Line: '.$mex->getLine());
			}
		}
		
		return $success;
	}
	
}
