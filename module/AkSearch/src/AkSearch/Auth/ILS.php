<?php
/**
 * Extended ILS authentication module
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
use VuFind\Auth\ILS as DefaultAuthILS;

class ILS extends DefaultAuthILS {
   
	
	/**
	 * Does this authentication method support user data changing?
	 *
	 * @return bool
	 */
	public function supportsLoanHistory() {
		// Check if the function exists in ILS Driver (e. g. Aleph)
		$supportsLoanHistory = false !== $this->getCatalog()->checkCapability('getLoanHistory');
		return $supportsLoanHistory;
	}
	

    /**
     * Does this authentication method support user data changing?
     *
     * @return bool
     */
    public function supportsUserDataChange() {
    	// Check if the function exists in ILS Driver (e. g. Aleph)
    	$supportsUserDataChange = false !== $this->getCatalog()->checkCapability('changeUserData');
    	return $supportsUserDataChange;
    }
    
    /**
     * Update user data from the request.
     *
     * @param \Zend\Http\PhpEnvironment\Request $request Request object containing new account details.
     * @throws \VuFind\Exception\Auth
     * @return array Result array containing 'success' (true or false) and 'status' (status message)
     */
    public function updateUserData($request) {
    	// 0. Click button in changeuserdata.phtml
		// 1. AkSitesController.php->changeUserDataAction()
		// 2. Manager.php->updateUserData()
		// 3. ILS.php->updateUserData()
		// 4. Aleph.php->changeUserData();

    	// Ensure that all expected parameters are populated to avoid notices in the code below.
    	$params = [];
    	foreach (['username', 'cudEmail', 'cudPhone', 'cudPhone2', 'address_type'] as $param) {
    		$params[$param] = $request->getPost()->get($param, '');
    	}
    	
    	$result = $this->getCatalog()->changeUserData([
    			'username'	=> $params['username'],
    			'email'		=> $params['cudEmail'],
    			'phone'		=> $params['cudPhone'],
    			'phone2'	=> $params['cudPhone2'],
    			'address_type'	=> $params['address_type']
    	]);

    	if (!$result['success']) {
    		throw new \VuFind\Exception\Auth($result['status']);
    	}
    	
    	return $result;
    }

}