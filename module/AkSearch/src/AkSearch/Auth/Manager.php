<?php
/**
 * Extended wrapper class for handling logged-in user in session.
 *
 * PHP version 5
 *
 * Copyright (C) AK Bibliothek Wien 2016.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software Foundation,
 * Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1335, USA.
 *
 * @category AKsearch
 * @package  Authentication
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://wien.arbeiterkammer.at/service/bibliothek/
 */

namespace AkSearch\Auth;
use VuFind\Cookie\CookieManager,
VuFind\Db\Table\User as UserTable,
Zend\Config\Config,
Zend\Session\SessionManager,
VuFind\Auth\Manager as DefaultAuthManager,
VuFind\Exception\Auth as AuthException,
VuFind\Db\Row\User as UserRow;


class Manager extends DefaultAuthManager {

	/**
	 * AKsearch.ini configuration
	 */
	protected $akConfig;	
	
	/**
	 * Overriding constructor to be able to get AKsearch.ini configuration.
	 * See also "getManager()" in "\AkSearch\Auth\Factory".
	 */
	public function __construct(\Zend\Config\Config $config, \VuFind\Db\Table\User $userTable, \Zend\Session\SessionManager $sessionManager, \VuFind\Auth\PluginManager $pm, \VuFind\Cookie\CookieManager $cookieManager, \Zend\Config\Config $akConfig) {
		$this->akConfig = $akConfig;
		
		// Call parent constructor
		parent::__construct($config, $userTable, $sessionManager, $pm, $cookieManager);	
	}
		
	
    /**
     * Is changing user data allowed?
     *
     * @param string $authMethod	E. g. ILS. This is optional. If set, checks the given auth method rather than the one in config file
     * @return bool
     */
    public function supportsUserDataChange($authMethod = null) {
    	
    	$methodExists = method_exists($this->getAuth($authMethod),'supportsUserDataChange');
    	
    	if ($methodExists) {
    		if ($this->getAuth($authMethod)->supportsUserDataChange()) {
    			$changeUserData = isset($this->akConfig->User->change_userdata) && $this->akConfig->User->change_userdata;
    			return $changeUserData;
    		}
    	}
    	
        return false;
    }
    
    
    /**
     * Update user data from the request.
     *
     * @param \Zend\Http\PhpEnvironment\Request $request Request object containing user data change details.
     * @throws AuthException
     * @return array Result array containing 'success' (true or false) and 'status' (status message)
     */
    public function updateUserData($request) {
        // 0. Click button in changeuserdata.phtml
		// 1. AkSitesController.php->changeUserDataAction()
		// 2. Manager.php->updateUserData()
		// 3. ILS.php/Database.php->updateUserData()
		// 4. Aleph.php/Alma.php->changeUserData();
        $result = $this->getAuth()->updateUserData($request);
        return $result;
    }
    
    
    /**
     * Is displaying loan history allowed
     * 
     * @param string $authMethod	E. g. ILS. This is optional. If set, checks the given auth method rather than the one in config file
     * @return bool
     */
    public function supportsLoanHistory($authMethod = null) {
    	$methodExists = method_exists($this->getAuth($authMethod),'supportsLoanHistory');
    	
    	if ($methodExists) {
    		if ($this->getAuth($authMethod)->supportsLoanHistory()) {
    			$loanHistory = isset($this->akConfig->User->loan_history) && $this->akConfig->User->loan_history;
    			return $loanHistory;
    		}
    	}
    	
    	return false;
    }
    
    
    public function getLoanHistory($user) {    	
    	$loanHistory = $this->getAuth()->getLoanHistory($user);
    	return $loanHistory;
    }
    
    
    public function setIsLoanHistory($user, $postParams) {
    	// 0. Click button in loanhistory.phtml
        // 1. AkSitesController.php->loanHistoryAction()
        // 2. Manager.php->setIsLoanHistory()
        // 3. Database.php->setIsLoanHistory()
        $result = $this->getAuth()->setIsLoanHistory($user, $postParams);
        return $result;
    }
    
    
    public function deleteLoanHistory($user) {
    	$result = $this->getAuth()->deleteLoanHistory($user);
        return $result;
    }
    
    
    public function requestSetPassword($username, $request) {
        // 0. Click button in requestsetpassword.phtml
        // 1. AkSitesController.php->requestSetPasswordAction()
        // 2. Auth\Manager.php->requestSetPassword()
        // 3. Auth\Database.php->requestSetPassword()
        $result = $this->getAuth()->requestSetPassword($username, $request);
        return $result;
    }
    
    
    public function setPassword($username, $hash, $request) {
        // 0. Click button in setpassword.phtml
        // 1. Controller\AkSitesController.php->setPasswordAction()
        // 2. Auth\Manager.php->setPassword()
        // 3. Auth\Database.php->setPassword()
    	$result = $this->getAuth()->setPassword($username, $hash, $request);
        return $result;
    }
    
    
    public function setPasswordWithOtp($request) {
    	// 0. Click button in setpasswordwithotp.phtml
    	// 1. AkSitesController.php->setPasswordWithOtpAction()
    	// 2. Manager.php->setPasswordWithOtp()
    	// 3. Database.php->setPasswordWithOtp()
    	$result = $this->getAuth()->setPasswordWithOtp($request);
    	return $result;
    }
    
}