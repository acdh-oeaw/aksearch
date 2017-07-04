<?php
namespace AkSearch\Controller;

use VuFind\Controller\AbstractBase;
use \ZfcRbac\Service\AuthorizationServiceAwareInterface, \ZfcRbac\Service\AuthorizationServiceAwareTrait;

// Hide all PHP errors and warnings as this could brake the JSON and XML output
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

class ApiController extends AbstractBase implements AuthorizationServiceAwareInterface {
	
	use AuthorizationServiceAwareTrait;
	
	protected $host;
	protected $database;
	protected $configAKsearch;
	protected $configAlma;
	private $auth;
	
	// Response to external
	protected $response;
	protected $headers;

	
	/**
	 * Constructor
	 */
	//public function __construct(\Zend\Config\Config $configAleph, \Zend\Config\Config $configAKsearch) {
	public function __construct(\VuFind\Config\PluginManager $configLoader) {
	
		//parent::__construct($configLoader);
		$configAleph = $configLoader->get('Aleph');
		$this->configAKsearch = $configLoader->get('AKsearch');
		$this->configAlma = $configLoader->get('Alma');
		
		date_default_timezone_set('Europe/Vienna');
		$this->host = rtrim(trim($configAleph->Catalog->host),'/');
		$this->database = $configAleph->Catalog->useradm;
		
		$this->response = $this->getResponse();
		$this->headers = $this->response->getHeaders();

		// Initialize the authorization service (for permissions)
		$init = new \ZfcRbac\Initializer\AuthorizationServiceInitializer();
		$init->initialize($this, $configLoader);
		$this->auth = $this->getAuthorizationService();
		if (!$this->auth) {
			throw new \Exception('Authorization service missing');
		}
	}
	
	public function webhookAction() {
		// Check if API is activated and permission is granted. If not, return the response that is already set in checkApi();
		if (!$this->checkApi('webhook', 'webhookPermission')) {
			return $this->response;
		}
		
		// Which user-api action should we perform (last part of URL) - configured in module.config.php
		$apiUserAction = $this->params()->fromRoute('apiWebhookAction');
		
		// Request from external
		$request = $this->getRequest();
		
		// Get request method (GET, POST, ...)
		$requestMethod = $request->getMethod();
		
		// Get request body if method is POST and is not empty
		$requestBodyArray = ($request->getContent() != null && !empty($request->getContent()) && $requestMethod == 'POST') ? json_decode($request->getContent(), true) : null;
		
		// Perform user-api action
		switch ($apiUserAction) {
			case 'Challenge':
				return $this->webhookChallenge();
				break;
			default:
				/*
				$this->headers->addHeaderLine('Content-type', 'text/plain');
				$this->response->setContent('API is working. No action defined with this request.');
				$this->response->setStatusCode(200); // Set HTTP status code to OK (200)
				return $this->response;
				*/
				break;
		}
	}
	
	/*
	private function webhookUserChange() {
		$this->headers->addHeaderLine('Content-type', 'text/plain');
		$this->response->setContent('WEBHOOK USER CHANGE');
		$this->response->setStatusCode(200); // Set HTTP status code to OK (200)
		return $this->response;
	}
	*/
	
	private function webhookChallenge($returnFormat = 'json') {
		
		// Check if the webhook secret is set
		$secret = (isset($this->configAlma->Webhook->secret)) ? $this->configAlma->Webhook->secret : null;
		if (!$secret) {
			$this->response->setStatusCode(403); // Set HTTP status code to Forbidden (403)
			$this->response->setContent('You have to define a secret in the [Webhook] section in Alma.ini! It has to be the same as in your webhook integration profile in Alma!');
			return $this->response;
		}
		
		// Create the return array
		$returnArray['challenge'] = $secret;
		$returnArray = array_filter($returnArray); // Remove null from array
		
		// Create return json value
		$returnJson = json_encode($returnArray,  JSON_PRETTY_PRINT);
		
		// Set HTTP status code to OK (200)
		$this->response->setStatusCode(200);
		
		// Return XML
		if ($returnFormat == 'xml') {
			$this->headers->addHeaderLine('Content-type', 'text/plain');
			$this->response->setContent('Only "json" response is supported at the moment!');
		} else if ($returnFormat == 'json') {
			// Return JSON (Default)
			$this->headers->addHeaderLine('Content-type', 'application/json');
			$this->headers->setHeader('Content-type', 'application/json');
			$this->response->setContent($returnJson);
		} else {
			$this->response->setContent('You have to define a valid response format! Only "json" is supported at the moment!');
		}
		
		return $this->response;
	}
	
	public function userAction() {
		
		// Check if API is activated and permission is granted. If not, return the response that is already set in checkApi();
		if (!$this->checkApi('user', 'userPermission')) {
			return $this->response;
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
				$this->headers->addHeaderLine('Content-type', 'text/plain');
				$this->response->setContent('API is working. No action defined with this request.');
				$this->response->setStatusCode(200); // Set HTTP status code to OK (200)
				return $this->response;
		}
	}
	

	private function userAuth($requestMethod, $host, $library, $username, $password, $returnFormat = 'json') {
		// Only POST requests are allowed
		if ($requestMethod != 'POST') {
			$this->headers->addHeaderLine('Content-type', 'text/plain');
			$this->response->setContent('Only POST requests are allowed.');
			$this->response->setStatusCode(405); // Set HTTP status code to Method Not Allowed (405)
			return $this->response; // Stop code execution here if request method is not POST
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
			$this->response->setStatusCode(500); // Default HTTP return status code: Internal Server Error (500)
			
			// Check if user exists or not and set HTTP status code for return headers
			if ($errorMsg == 'Error in Verification') {
				$userExists = 'N'; // Set from default "U" to "N" as 'Error in Verification' tells us that the user credentials does not exist
				$this->response->setStatusCode(401);
			} else if ($errorMsg == 'Both Bor_Id and Verification must be supplied') {
				$this->response->setStatusCode(401); // At least one part of the user credentials was not supplied
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
				$this->response->setStatusCode(200); // Set HTTP status code to OK (200)
			} else {
				$isValid = 'N'; // The user is NOT valid (expired or has blocks) and is NOTallowed to access the resource
				$this->response->setStatusCode(403); // Set HTTP status code to Forbidden (403)
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
			$this->headers->addHeaderLine('Content-type', 'text/plain');
			$this->response->setContent('Only "json" response is supported at the moment!');
			// $headers->addHeaderLine('Content-type', 'text/xml');
			// $this->response->setContent($XML);
		} else if ($returnFormat == 'json') {
			// Return JSON (Default)
			$this->headers->addHeaderLine('Content-type', 'application/json');
			$this->response->setContent($returnJson);
		} else {
			$this->response->setContent('You have to define a valid response format! Only "json" is supported at the moment!');
		}
		
		return $this->response;
	}
	
	
	private function callX($host, $params = null, $https = true, $method = 'GET', $data = false) {
		// Create URL for the curl call
		$paramList = (!empty($params) && $params != null) ? http_build_query($params) : [];
		$baseXUrl = ($https) ? 'https://'.$host.'/X' : 'http://'.$host.'/X';
		$url = sprintf('%s?%s', $baseXUrl, $paramList);
		
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
			$this->headers->addHeaderLine('Content-type', 'text/plain');
			$this->response->setContent('API "'.$apiName.'" is not activated in AKsearch.ini');
			$this->response->setStatusCode(403); // Set HTTP status code to Forbidden (403)
			//return $this->response; // Stop code execution here if user API is not activated
		} else { // If API is activated, check if the permission is granted (using "userPermission" in AKsearch.ini and the settings in permissions.ini)
			$permissionIsGranted = $this->isApiAccessAllowed($apiPermissionName);
			if (!$permissionIsGranted) {
				$this->headers->addHeaderLine('Content-type', 'text/plain');
				$this->response->setContent('Access to API is not allowed.');
				$this->response->setStatusCode(403); // Set HTTP status code to Forbidden (403)
				//return $this->response; // Stop code execution here if permission = false
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
	
}