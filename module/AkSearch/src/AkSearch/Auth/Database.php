<?php

namespace AkSearch\Auth;
use VuFind\Exception\Auth as AuthException;
use Zend\Crypt\Password\Bcrypt;
use VuFind\Auth\Database as DefaultDatabaseAuth;
use Zend\Crypt\Symmetric\Mcrypt;
use Zend\Crypt\BlockCipher as BlockCipher;


class Database extends DefaultDatabaseAuth implements \Zend\ServiceManager\ServiceLocatorAwareInterface {
	
	use \Zend\ServiceManager\ServiceLocatorAwareTrait;
	
	/**
	 * Is encryption enabled?
	 *
	 * @var bool
	 */
	protected $encryptionEnabled = null;
	
	/**
	 * Encryption key used for catalog passwords (null if encryption disabled):
	 *
	 * @var string
	 */
	protected $encryptionKey = null;
	
	/**
	 * VuFind configuration
	 *
	 * @var \Zend\Config\Config
	 */
	protected $config = null;
	
	/**
	 * Alma configuration
	 *
	 * @var \Zend\Config\Config
	 */
	protected $almaConfig = null;
		

    /**
     * Create a new user account from the request. Exteded version for use with Alma
     *
     * @param \Zend\Http\PhpEnvironment\Request $request Request object containing
     * new account details.
     *
     * @throws AuthException
     * @return \VuFind\Db\Row\User New user row.
     */
    public function create($request) {
    	// Get Alma.ini via service locator (getServiceLocator()).
    	// Attention: Class must implement \Zend\ServiceManager\ServiceLocatorAwareInterface and use \Zend\ServiceManager\ServiceLocatorAwareTrait
    	$parentLocator = $this->getServiceLocator()->getServiceLocator();
    	$this->almaConfig= $parentLocator->get('VuFind\Config')->get('Alma');
    	
    	// Check if we should use the VuFind database to store user credentials.
    	$useVuFindDatabase = (isset($this->almaConfig->Authentication->useVuFindDatabase)) ? filter_var($this->almaConfig->Authentication->useVuFindDatabase, FILTER_VALIDATE_BOOLEAN) : false;
    	
    	// Call the default "create" function if the configuration "useVuFindDatabase" in Alma.ini is set to false
    	if (!$useVuFindDatabase) {
    		parent::create($request);
    		return; // Stop execution
    	}
    	
        // Ensure that all expected parameters are populated to avoid notices in the code below.
        $params = [
            'firstname' => '', 'lastname' => '', 'username' => '',
            'password' => '', 'password2' => '', 'email' => ''
        ];
        foreach ($params as $param => $default) {
            $params[$param] = $request->getPost()->get($param, $default);
        }
        
        // Validate Input
        $this->validateUsernameAndPassword($params);

        // Invalid Email Check
        $validator = new \Zend\Validator\EmailAddress();
        if (!$validator->isValid($params['email'])) {
            throw new AuthException('Email address is invalid');
        }
        if (!$this->emailAllowed($params['email'])) {
            throw new AuthException('authentication_error_creation_blocked');
        }

        // Make sure we have a unique username
        $table = $this->getUserTable();
        if ($table->getByUsername($params['username'], false)) {
            throw new AuthException('That username is already taken');
        }
        // Make sure we have a unique email
        if ($table->getByEmail($params['email'])) {
            throw new AuthException('That email address is already used');
        }

        // If we got this far, we're ready to create the account:
        $user = $table->createRowForUsername($params['username']);
        $user->firstname = $params['firstname'];
        $user->lastname = $params['lastname'];
        $user->email = $params['email'];
        if ($this->passwordHashingEnabled()) {
            $bcrypt = new Bcrypt();
            $user->pass_hash = $bcrypt->create($params['password']);
        } else {
            $user->password = $params['password'];
        }
        //$user->save(); // Save later after creating ILS login
        
        // Create also an ILS login (used for Alma). We use the same username and password as for the VuFind credentials.
        $user->cat_username = $params['username'];
        if ($this->passwordEncryptionEnabled()) {
        	$user->cat_password = null;
        	$user->cat_pass_enc = $this->encryptOrDecrypt($params['password'], true);
        } else {
        	$user->cat_password = $params['password'];
        	$user->cat_pass_enc = null;
        }
        
        // Save user entry
        $user->save();
        
        // TODO: Check if we should update the entries in the library card table
        // See \VuFind\Db\Row\User->updateLibraryCardEntry()

        return $user;
    }

    
    /**
     * Update a user's password from the request.
     *
     * @param \Zend\Http\PhpEnvironment\Request $request Request object containing
     * new account details.
     *
     * @throws AuthException
     * @return \VuFind\Db\Row\User New user row.
     */
    public function updatePassword($request) {
        // Ensure that all expected parameters are populated to avoid notices in the code below.
        $params = [
            'username' => '', 'password' => '', 'password2' => ''
        ];
        foreach ($params as $param => $default) {
            $params[$param] = $request->getPost()->get($param, $default);
        }

        // Validate Input
        $this->validateUsernameAndPassword($params);

        // Create the row and send it back to the caller:
        $table = $this->getUserTable();
        $user = $table->getByUsername($params['username'], false);
        if ($this->passwordHashingEnabled()) {
            $bcrypt = new Bcrypt();
            $user->pass_hash = $bcrypt->create($params['password']);
        } else {
            $user->password = $params['password'];
        }
        $user->save();
        return $user;
    }
    
    
    /**
     * Is ILS password encryption enabled?
     *
     * @return bool
     */
    public function passwordEncryptionEnabled() {

    	// Set class variable $this->config because we will need it also in function encryptOrDecrypt()
    	$this->config = $this->getConfig(); // Get config.ini from extended Database class

    	if ($this->encryptionEnabled === null) {
    		$this->encryptionEnabled = isset($this->config->Authentication->encrypt_ils_password) ? $this->config->Authentication->encrypt_ils_password: false;
    	}    	
    	return $this->encryptionEnabled;
    }
    
    
    /**
     * This is a central function for encrypting and decrypting so that
     * logic is all in one location
     *
     * @param string $text    The text to be encrypted or decrypted
     * @param bool   $encrypt True if we wish to encrypt text, False if we wish to
     * decrypt text.
     *
     * @return string|bool    The encrypted/decrypted string
     * @throws \VuFind\Exception\PasswordSecurity
     */
    protected function encryptOrDecrypt($text, $encrypt = true) {
    	// Ignore empty text:
    	if (empty($text)) {
    		return $text;
    	}
    	
    	// Load encryption key from configuration if not already present:
    	if (null === $this->encryptionKey) {
    		if (!isset($this->config->Authentication->ils_encryption_key) || empty($this->config->Authentication->ils_encryption_key)) {
    			throw new \VuFind\Exception\PasswordSecurity('ILS password encryption on, but no key set.');
    		}
    		$this->encryptionKey = $this->config->Authentication->ils_encryption_key;
    	}
    	
    	// Perform encryption:
    	$cipher = new BlockCipher(new Mcrypt(['algorithm' => 'blowfish']));
    	$cipher->setKey($this->encryptionKey);
    	return $encrypt ? $cipher->encrypt($text) : $cipher->decrypt($text);
    }

}
