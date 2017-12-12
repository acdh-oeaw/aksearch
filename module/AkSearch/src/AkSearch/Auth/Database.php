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
	 * ILS connection
	 * 
	 * @var \VuFind\ILS\Connection
	 */
	protected $catalog = null;
		

    /**
     * Create a new user account from the request. Exteded version for use with Alma.
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
    	$this->almaConfig = $parentLocator->get('VuFind\Config')->get('Alma');
    	
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
     * Attempt to authenticate the current user. Throws exception if login fails.
     * Exteded version for use with Alma.
     *
     * @param \Zend\Http\PhpEnvironment\Request $request Request object containing
     * account credentials.
     *
     * @throws AuthException
     * @return \VuFind\Db\Row\User Object representing logged-in user.
     */
    public function authenticate($request) {
    	// Make sure the credentials are non-blank:
    	$this->username = trim($request->getPost()->get('username'));
    	$this->password = trim($request->getPost()->get('password'));
    	if ($this->username == '' || $this->password == '') {
    		throw new AuthException('authentication_error_blank');
    	}
    	
    	// Get user data from database
    	$user = $this->getUserTable()->getByUsername($this->username, false);
    
    	// Check if the user should be forced to change his password
    	if (is_object($user) && $this->isForcePwChange($user)) {
    	    // Return user object with "$user->force_pw_change" set to "1"
    	    return $user;
    	}
    	
    	// Validate the credentials
    	if (!is_object($user) || !$this->checkPassword($this->password, $user)) {
    		throw new AuthException('authentication_error_invalid');
    	}
    	
    	// Check if password is a one-time-password
    	if (!is_object($user) || $this->isOtp($user)) {
    		throw new AuthException('authentication_error_otp');
    	}
    	    	
    	// If we got this far, the login was successful:
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
    
    
    protected function isOtp($user) {
    	return (isset($user->is_otp)) ? filter_var($user->is_otp, FILTER_VALIDATE_BOOLEAN) : false;
    }
    
    
    protected function isForcePwChange($user) {
        return (isset($user->force_pw_change)) ? filter_var($user->force_pw_change, FILTER_VALIDATE_BOOLEAN) : false;
    }
    
    
    public function requestSetPassword($request) {
        // 0. Click button in requestsetpassword.phtml
        // 1. AkSitesController.php->requestSetPasswordAction()
        // 2. Auth\Manager.php->requestSetPassword()
        // 3. Auth\Database.php->requestSetPassword()        
        
        // Create an array for the values that we need from the post request
        $params = [];
        foreach (['username', 'email'] as $param) {
            $params[$param] = $request->getPost()->get($param, '');
        }
        
        // Needs a username
        if (trim($params['username']) == '') {
            throw new AuthException('required_fields_empty');
        }
        
        // Needs an email address
        if (trim($params['email']) == '') {
            throw new AuthException('required_fields_empty');
        }
        
        $user = $this->getDbTableManager()->get('user')->getByUsernameAndEmail($params['username'], $params['email']);
        
        // Check if user was found in database
        if (!is_object($user)) {
            // User was not found, so return an error message
            return array('success' => false, 'status' => 'error_request_set_password', 'user' => null);
        }
        
        // User was found, so return a success message
        return array('success' => true, 'status' => 'success_request_set_password', 'user' => $user);
    }
    
    
    public function setPassword($username, $hash, $request) {
        // 0. Click button in setpassword.phtml
        // 1. Controller\AkSitesController.php->setPasswordAction()
        // 2. Auth\Manager.php->setPassword()
        // 3. Auth\Database.php->setPassword()
        
        // Create an array for the values that we need from the post request
        $params = [];
        foreach (['newPassword', 'newPasswordConfirm'] as $param) {
            $params[$param] = $request->getPost()->get($param, '');
        }
        
        // Needs new passwords
        if ($params['newPassword'] == '' || $params['newPasswordConfirm'] == '') {
            throw new AuthException('required_fields_empty');
        }
        
        // New passwords don't match
        if ($params['newPassword'] != $params['newPasswordConfirm']) {
            throw new AuthException('Passwords do not match');
        }
        
        // Get user data from database
        $user = $this->getUserTable()->getByUsername($username, false);
        //$user = $this->getDbTableManager()->get('user')->getByUsernameAndEmail($username, $email);
        
        // Control the user by verify hash as a security measure
        $controlUser = $this->getUserTable()->getByVerifyHash($hash);        
        
        // Check if user was found in database
        if (!is_object($user) || !is_object($controlUser)) {
        	$result = array('success' => false, 'status' => 'authentication_error_invalid');
        } else if ($user->verify_hash != $controlUser->verify_hash && $user->username != $controlUser->username) {
        	$result = array('success' => false, 'status' => 'authentication_error_invalid');
        } else { // Set the password

        	if ($this->passwordHashingEnabled()) {
        		$bcrypt = new Bcrypt();
        		$user->pass_hash = $bcrypt->create($params['newPassword']);
        	} else {
        		$user->password = $params['newPassword'];
        	}
        	$user->force_pw_change = 0;
        	$user->verify_hash = '';
        	$user->save();
        	
        	$result = array('success' => true, 'status' => 'setPasswordSuccess');
        }
        
        return $result;
    }
    
    
    public function setPasswordWithOtp($request) {
		// 0. Click button in setpasswordwithotp.phtml
		// 1. AkSitesController.php->setPasswordWithOtpAction()
		// 2. Manager.php->setPasswordWithOtp()
		// 3. Database.php->setPasswordWithOtp()
		
    	// Create an array for the values that we need from the post request
		$params = [];
		foreach (['username', 'oneTimePassword', 'newPassword', 'newPasswordConfirm'] as $param) {
			$params[$param] = $request->getPost()->get($param, '');
		}
		
		// Needs a username
		if (trim($params['username']) == '') {
			throw new AuthException('required_fields_empty');
		}
		// Needs a one-time-password
		if (trim($params['oneTimePassword']) == '') {
			throw new AuthException('required_fields_empty');
		}
		// Needs new passwords
		if ($params['newPassword'] == '' || $params['newPasswordConfirm'] == '') {
			throw new AuthException('required_fields_empty');
		}
		// New passwords don't match
		if ($params['newPassword'] != $params['newPasswordConfirm']) {
			throw new AuthException('Passwords do not match');
		}
		
		// Get user data from database
		$user = $this->getUserTable()->getByUsername($params['username'], false);
		
		// Check if user was found in database
		if (!is_object($user)) {
			return array('success' => false, 'status' => 'authentication_error_invalid');
		}
		
		// Do the OTP validation
		$result = $this->validateOtp($user, $params);
		
		// Set the password on successful otp validation
		if ($result['success']) {
			if ($this->passwordHashingEnabled()) {
				$bcrypt = new Bcrypt();
				$user->pass_hash = $bcrypt->create($params['newPassword']);
			} else {
				$user->password = $params['newPassword'];
			}
			$user->is_otp = 0;
			$user->save();
		}
			
		return $result;
    }
    
    
    protected function validateOtp($user, $params) {
    	$isValid = false;
    	$message = null;

    	// Check if password in user database is a otp
    	$isOtp = filter_var($user->is_otp, FILTER_VALIDATE_BOOLEAN);
    	if (!$isOtp) {
    		return array('success' => $isValid, 'status' => 'authentication_error_not_an_otp');
    	}
    	
    	// Check if password hashing is enabled
    	if ($this->passwordHashingEnabled()) {
    		if ($user->password) {
    			throw new \VuFind\Exception\PasswordSecurity('Unexpected unencrypted password found in database');
    		}
    		
    		// Verify hashed password
    		$bcrypt = new Bcrypt();
    		$isValid = $bcrypt->verify($params['oneTimePassword'], $user->pass_hash);
    	} else {
    		// Verify clear text password
    		$isValid = ($params['oneTimePassword'] == $user->password);
    	}
    	
    	return array('success' => $isValid, 'status' => (($isValid) ? 'authentication_otp_success' : 'authentication_otp_error'));
    }
    
    
    /**
     * Update eMail address in VuFind database and multiple other userdata (phone 1, phone 2) in Alma if applicable.
     * 
     * @param array $request	An array of POSTed user data from the "changeuserdata" form.
	 * @return array			An array of data on the request including whether or not it was successful and a system message (if available)
     */
    public function updateUserData($request) {
    	// 0. Click button in changeuserdata.phtml
		// 1. AkSitesController.php->changeUserDataAction()
		// 2. Manager.php->updateUserData()
		// 3. ILS.php/Database.php->updateUserData()
		// 4. Aleph.php/Alma.php->changeUserData();
		
    	// Get Alma.ini via service locator (getServiceLocator()).
    	// Attention: Class must implement \Zend\ServiceManager\ServiceLocatorAwareInterface and use \Zend\ServiceManager\ServiceLocatorAwareTrait
    	$parentLocator = $this->getServiceLocator()->getServiceLocator();
    	$this->almaConfig = $parentLocator->get('VuFind\Config')->get('Alma');
    	
    	// Ensure that all expected parameters are populated to avoid notices in the code below.
    	$params = [];
    	foreach (['username', 'cudEmail', 'cudPhone', 'cudPhone2'] as $param) {
    		$params[$param] = $request->getPost()->get($param, '');
    	}

    	if ((!isset($params['cudEmail']) || empty(trim($params['cudEmail']))) || (!isset($params['username']) || empty($params['username']))) {
    		$statusMessage = 'required_fields_empty';
    		return array('success' => $success, 'status' => $statusMessage);
    	}
    	
    	$isAlma = (isset($this->config->Catalog->driver) && strtolower($this->config->Catalog->driver) == 'alma') ? true : false;
    	$useVuFindDatabase = (isset($this->almaConfig->Authentication->useVuFindDatabase)) ? filter_var($this->almaConfig->Authentication->useVuFindDatabase, FILTER_VALIDATE_BOOLEAN) : false;
    	
    	// Get user from VuFind database
    	$table = $this->getUserTable();
    	$user = $table->getByUsername($params['username'], false);
    	$params['primaryId'] = $user->cat_id;
    	
    	// Update eMail address in VuFind database
    	$user->email = $params['cudEmail'];
    	$user->save(); // Save user entry
    	$result = array('success' => true, 'status' => 'changed_userdata_success');
    	
    	// Update userdata also in Alma if the Alma driver is activated and if the authentication source is the VuFind database.
    	// If another authentication source is used (e. g. LDAP), we don't update Alma because a synchronisazion process apart
    	// from VuFind should do that job.
    	if ($isAlma && $useVuFindDatabase) {
    		$result = $this->catalog->changeUserData([
    				'primaryId'	=> $params['primaryId'],
    				'username'	=> $params['username'],
    				'email'		=> $params['cudEmail'],
    				'phone'		=> $params['cudPhone'],
    				'phone2'	=> $params['cudPhone2']
    		]);
    	}
    	
    	return $result;
    }
    

    /**
     * Does this authentication method support user data changing?
     * 
     * IMPORTANT: As user data are normally stored in ILS, you have also to activate an ILS that supports the
     * change of user data. User data should be updated in the VuFind Database AND in the ILS (e. g. the eMail-Address).
     *
     * @return bool
     */
    public function supportsUserDataChange() {
    	// Get ILS via service locator
    	// Attention: Class must implement \Zend\ServiceManager\ServiceLocatorAwareInterface and use \Zend\ServiceManager\ServiceLocatorAwareTrait
    	$parentLocator = $this->getServiceLocator()->getServiceLocator();
    	$this->catalog = $parentLocator->get('VuFind\ILSConnection');

    	$supportsUserDataChange = false !== $this->catalog->checkCapability('changeUserData');
    	return $supportsUserDataChange;
    }
    
    
    public function isLoanHistory($profile) {
        // Get user data from database
        $user = $this->getUserTable()->getByUsername($profile['barcode'], false);
        
        // Check if the user has chosen to save the loan history
        return (isset($user->save_loans)) ? filter_var($user->save_loans, FILTER_VALIDATE_BOOLEAN) : false;
    }
    

    public function getLoanHistory($profile) {
        $loanHistoryArray = [];
        
        
        if (!$this->isLoanHistory($profile)) {
            $loanHistoryArray['isLoanHistory'] = false;
            return $loanHistoryArray;
        }
        
        
    	$table = $this->getLoansTable();
    	$loans = $table->getByIlsUserId($profile['id']);
    	
    	if ($loans) {
    		foreach ($loans as $loan) {
    			
    			$dueDate = (isset($loan['due_date'])) ? $loan['due_date'] : null;
    			$dueTime = (isset($loan['due_date'])) ? $loan['due_date'] : null;
    			$id = (isset($loan['item_id'])) ? $loan['item_id'] : null; // For Alma, this is the MMS ID!
    			$barcode = (isset($loan['barcode'])) ? $loan['barcode'] : null;
    			$publicationYear = (isset($loan['publication_year'])) ? $loan['publication_year'] : null;
    			$title = (isset($loan['title'])) ? $loan['title'] : null;
    			//$loanId = (isset($loan['ils_loan_id'])) ? $loan['ils_loan_id'] : null;
    			$author = (isset($loan['author'])) ? $loan['author'] : null;
    			$location = (isset($loan['location_code'])) ? $loan['location_code'] : null;
    			$loanDate = (isset($loan['loan_date'])) ? $loan['loan_date'] : null;

    			$loanHistoryArray[] = [
    					'duedate' => (($dueDate != null) ? $this->parseDate($dueDate, true) : null),
    					//'dueTime' => (($dueTime!= null) ? $this->parseTime($dueTime) : null),
    					'loan_date' => (($loanDate != null) ? $this->parseDate($loanDate, true) : null),
    					'id' => $id,
    					//'source' => '',
    					'barcode' => $barcode,
    					//'renew' => '',
    					//'renewLimit' => '',
    					//'request' => '',
    					//'volume' => '',
    					'publication_year' => $publicationYear,
    					//'renewable' => $renewable,
    					//'message' => $message,
    					'title' => $title,
    					//'item_id' => $loanId,
    					//'institution_name' => '',
    					//'isbn' => [$isbn],
    					//'issn' => ,
    					//'oclc' => '',
    					//'upc' => '',
    					//'borrowingLocation' => '',
    					
    					// Other values that are not defined in the VuFind default return array for getMyTransactions()
    					'author' => $author,
    					'location' => $location,
    					
    					//'reqnum' => $reqnum,
    					//'returned' => $this->parseDate($returned),
    					//'type' => $type,
    			];
    		}
    	}
    	
    	return $loanHistoryArray;
    }
    
    
    /**
     * Get access to the loans table.
     *
     * @return \AkSearch\Db\Table\Loans
     */
    public function getLoansTable() {
    	return $this->getDbTableManager()->get('Loans');
    }
    
    
    /**
     * Does this authentication method support showing the loan history?
     *
     * @return bool
     */
    public function supportsLoanHistory() {
    	return true;
    }
    
    
    /**
     * Parse a date.
     *
     * @param string $date Date to parse
     *
     * @return string
     */
    public function parseDate($date, $withTime = false) {
    	// Get the date converter
    	$parentLocator = $this->getServiceLocator()->getServiceLocator();
    	$dateConverter = $parentLocator->get('VuFind\DateConverter');
    	
    	
    	// Remove trailing Z from end of date (e. g. from Alma we get dates like 2012-07-13Z - without a time, this is wrong):
    	if (strpos($date, 'Z', (strlen($date)-1))) {
    		$date = preg_replace('/Z{1}$/', '', $date);
    	}
    	
    	if ($date == null || $date == '') {
    		return '';
    	} else if (preg_match("/^[0-9]{8}$/", $date) === 1) { // 20120725
    		return $dateConverter->convertToDisplayDate('Ynd', $date);
    	} else if (preg_match("/^[0-9]+\/[A-Za-z]{3}\/[0-9]{4}$/", $date) === 1) {
    		// 13/jan/2012
    		return $dateConverter->convertToDisplayDate('d/M/Y', $date);
    	} else if (preg_match("/^[0-9]+\/[0-9]+\/[0-9]{4}$/", $date) === 1) {
    		// 13/7/2012
    		return $dateConverter->convertToDisplayDate('d/m/Y', $date);
    	} else if (preg_match("/^[0-9]{1,2}\/[0-9]{1,2}\/[0-9]{2,4}$/", $date) === 1) { // added by AK Bibliothek Wien - FOR GERMAN ALEPH DATES
    		// 13/07/2012
    		return $dateConverter->convertToDisplayDate('d/m/y', $date);
    	} else if (preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/", $date) === 1) { // added by AK Bibliothek Wien - FOR GERMAN ALMA DATES WITHOUT TIME - Trailing Z is removed above
    		// 2012-07-13[Z] - Trailing Z is removed above
    		return $dateConverter->convertToDisplayDate('Y-m-d', $date);
    	} else if (preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}$/", substr($date, 0, 19)) === 1) { // added by AK Bibliothek Wien - FOR GERMAN ALMA DATES WITH TIME - Trailing Z is removed above
    		// 2017-07-09T18:00:00[Z] - Trailing Z is removed above
    		if ($withTime) {
    			//2017-06-19T21:59:00[Z] - Trailing Z is removed above
    			return $dateConverter->convertToDisplayDateAndTime('Y-m-d\TH:i:s', substr($date, 0, 19));
    		} else {
    			return $dateConverter->convertToDisplayDate('Y-m-d', substr($date, 0, 10));
    		}
    	} else {
    		throw new \Exception("Invalid date: $date");
    	}
    }
    

}
