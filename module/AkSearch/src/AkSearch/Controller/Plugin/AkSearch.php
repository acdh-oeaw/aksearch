<?php

namespace AkSearch\Controller\Plugin;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Config\Config;
use \VuFind\Db\Table\PluginManager as DbTablePluginManager;
use Zend\Crypt\Password\Bcrypt;
use \VuFindHttp\HttpService;
use \VuFind\Exception\ILS as ILSException;

class AkSearch extends AbstractPlugin implements \Zend\Log\LoggerAwareInterface {
	
	use \VuFind\Log\LoggerAwareTrait;
	
	/**
	 * VuFind configuration
	 * 
	 * @var Config
	 */
	protected $config;
	
	/**
	 * Alma configuration
	 * 
	 * @var Config
	 */
	protected $configAlma;
	
	/**
	 * User database table
	 * 
	 * @var \AkSearch\Db\Table\User
	 */
	protected $userTable;
	
	/**
	 * Http service
	 * 
	 * @var \VuFindHttp\HttpService
	 */
	protected $httpService;
	
	/**
	 * Check if debuging is enabled
	 * 
	 * @var boolean
	 */
	protected $debugEnabled;
	
		
	/**
	 * Constructor
	 *
	 * @param Config $config Configuration
	 */
	public function __construct(Config $config, Config $configAlma, DbTablePluginManager $dbTableManager, HttpService $httpService) {
		$this->config = $config;
		$this->configAlma = $configAlma;
		$this->userTable = $dbTableManager->get('user');
		$this->httpService = $httpService;
		$this->debugEnabled = filter_var($config->System->debug, FILTER_VALIDATE_BOOLEAN);
	}
	
	/**
	 * Generate a barcode value with the help of md5 hashing.
	 * 
	 * @param	string	$stringForHash	A string that should be unique (e. g. eMail address + timestamp) from which a barcode (hash) value will be generated
	 * @return	string	The barcode value
	 */
	public function generateBarcode($stringForHash) {
		$barcodePrefix = (isset($this->configAlma->Webhook->barcodePrefix)) ? $this->configAlma->Webhook->barcodePrefix : ''; // Default: No prefix
		$barcodeLength = (isset($this->configAlma->Webhook->barcodeLength) || $this->configAlma->Webhook->barcodeLength > 32) ? $this->configAlma->Webhook->barcodeLength : 10;
		$hash = substr(md5($stringForHash), 0, $barcodeLength);
		$barcode = strtoupper($barcodePrefix . $hash);
		return $barcode;
	}
	
	/**
	 * Check if password hashing is enabled in config.ini
	 *
	 * @return bool
	 */
	public function passwordHashingEnabled() {
		$configValue = (isset($this->config->Authentication->hash_passwords)) ? filter_var($this->config->Authentication->hash_passwords, FILTER_VALIDATE_BOOLEAN) : null;
		return ($configValue != null) ? $configValue : false;
	}
	
	
	/**
	 * Get the config in Alma.ini
	 * 
	 * @return \Zend\Config\Config
	 */
	public function getAlmaConfig() {
		return $this->configAlma;
	}
	
	/**
	 * Get the config in config.ini
	 *
	 * @return \Zend\Config\Config
	 */
	public function getConfig() {
		return $this->config;
	}
	
	
	/**
	 * Update user or create (if does not exist) in 'user' database table.
	 * 
	 * @param string $primaryId
	 * @param string $barcode
	 * @param boolean $createIfNotExist
	 * 
	 * @return \VuFind\Db\Row\User | null
	 */
	public function createOrUpdateUserInDb($firstName, $lastName, $eMail, $password = null, $isOtp = true, $primaryId = null, $barcode = null, $loanHistory = false, $createIfNotExist = true) {
		
		// Get user from database table if he exists there. If not, this creates and gets a new user object.
		$user = ($primaryId != null) ? $this->userTable->getByCatalogId($primaryId, $barcode, $createIfNotExist) : null;
		
		// If everything went right, we have a user object now
		if ($user != null && !empty($user)) {
			$user->cat_id = $primaryId;
			$user->username = $barcode;
			$user->firstname = $firstName;
			$user->lastname = $lastName;
			$user->email = $eMail;
			$user->cat_username = $barcode;
			if ($loanHistory != null) {
				$user->save_loans = (($loanHistory) ? 1 : 0);
			}
			
			// Do the password hashing if enabled
			if ($password != null) {
				if ($this->passwordHashingEnabled()) { // See Controller\Plugin\AkSearch.php
					$bcrypt = new Bcrypt();
					$passHash = $bcrypt->create($password);
					$user->pass_hash = $passHash;
				} else {
					$user->password = $password;
				}
				$user->is_otp = (($isOtp) ? 1 : 0);
			}
			
			$user->save(); // Update user record
		}
		
		return $user;
	}
	
	
	/**
	 * Execute an HTTP request. Mainly used for communicating with ILS systems via API.
	 * 
	 * @param string $url
	 * @param string $method
	 * @param string $rawBody
	 * @param string $headers
	 * 
	 * @throws ILSException
	 * @return array
	 */
	public function doHTTPRequest($url, $method = 'GET', $rawBody = null, $headers = null) {
		
		if ($this->debugEnabled) {
			$this->debug("URL: '$url'");
		}
		
		$result = null;
		$statusCode = null;
		$returnArray = null;
		
		try {
			$client = $this->httpService->createClient($url);
			$client->setMethod($method);
			$client->setOptions(array('timeout' => 30)); // Increas timeout to 30 seconds
			
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
		if ($this->debugEnabled) {
			$this->debug("URL: $url response: $answer (HTTP status code: $statusCode)");
		}
		
		$answer = str_replace('xmlns=', 'ns=', $answer);
		$xml = simplexml_load_string($answer);
		
		if (!$xml && $result->isServerError()) {
			if ($this->debugEnabled) {
				$this->debug("XML is not valid or HTTP error, URL: $url, HTTP status code: $statusCode");
			}
			throw new ILSException("XML is not valid or HTTP error, URL: $url method: $method answer: $answer, HTTP status code: $statusCode.");
		}
		
		$returnArray = ['xml' => $xml, 'status' => $statusCode];
		
		return $returnArray;
	}
	
	
	

	
}
?>