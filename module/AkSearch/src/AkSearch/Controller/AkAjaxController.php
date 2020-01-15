<?php

/**
 * Extended Ajax Controller Module
 *
 * PHP version 5
 *
 * Copyright (C) AK Bibliothek Wien 2015.
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
 * @package  Controller
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://wien.arbeiterkammer.at/service/bibliothek/
 */
namespace AkSearch\Controller;
use VuFind\Exception\Auth as AuthException;

class AkAjaxController extends \VuFind\Controller\AjaxController implements \VuFindHttp\HttpServiceAwareInterface {
	
	use \VuFindHttp\HttpServiceAwareTrait;
	
	// Additional status constants
	const STATUS_FORCE_PW_CHANGE = 'FPWC';                  // force password change

	
	/**
	 * Login with post'ed username and encrypted password.
	 * Updated for use with Alma.
	 *
	 * @return \Zend\Http\Response
	 */
	protected function loginAjax() {
	    
	    // Fetch Salt
	    $salt = $this->generateSalt();
	    
	    // HexDecode Password
	    $password = pack('H*', $this->params()->fromPost('password'));
	    
	    // Decrypt Password
	    $password = base64_decode(\VuFind\Crypt\RC4::encrypt($salt, $password));
	    
	    // Update the request with the decrypted password:
	    $this->getRequest()->getPost()->set('password', $password);
	    
	    // Get username
	    $username = $this->params()->fromPost('username');
	    
	    // Authenticate the user:
	    try {
	        $user = $this->getAuthManager()->login($this->getRequest());
	        
	        // Check if user should be forced to change his password
	        $forcePwChange = (isset($user->force_pw_change)) ? filter_var($user->force_pw_change, FILTER_VALIDATE_BOOLEAN) : false;
	        if ($forcePwChange) {
	            // Log out the user and destroy the user session
	            $this->getAuthManager()->logout(null, true);
	            
	            // Send the user to a site where he will be able to change his password
	            return $this->output($username, self::STATUS_FORCE_PW_CHANGE);
	        }
	    } catch (AuthException $e) {
	        return $this->output(
	            $this->translate($e->getMessage()),
	            self::STATUS_ERROR
	            );
	    }
	    
	    return $this->output(true, self::STATUS_OK);
	}
	
	
	/**
	 * Call entity facts API
	 * Example: http://hub.culturegraph.org/entityfacts/118540238
	 *
	 * @return JSON
	 */
	public function getEntityFactAjax() {
		$this->outputMode = 'json';
		$gndid = $this->params()->fromQuery('gndid');
		$client = $this->getServiceLocator()->get('VuFind\Http')->createClient('http://hub.culturegraph.org/entityfacts/' . $gndid);
		$client->setMethod('GET');
		$result = $client->send();
		
		// If an error occurs
		if (! $result->isSuccess()) {
			return $this->output($this->translate('An error has occurred'), self::STATUS_ERROR);
		}
		
		$json = $result->getBody();
		return $this->output($json, self::STATUS_OK);
	}
	
/*
	public function getItemAvailabilityAjax() {
		$this->outputMode = 'json';
		$data = $this->params()->fromQuery('itemId');
		return $this->output($data, self::STATUS_OK);
		
		
// 		$gndid = $this->params()->fromQuery('gndid');
// 		$client = $this->getServiceLocator()->get('VuFind\Http')->createClient('http://hub.culturegraph.org/entityfacts/' . $gndid);
// 		$client->setMethod('GET');
// 		$result = $client->send();
		
// 		// If an error occurs
// 		if (! $result->isSuccess()) {
// 			return $this->output($this->translate('An error has occurred'), self::STATUS_ERROR);
// 		}
		
// 		$json = $result->getBody();
// 		return $this->output($json, self::STATUS_OK);
		
	}
*/

	
	/**
	 * Check Request is Valid
	 *
	 * @return \Zend\Http\Response
	 */
	protected function checkRequestIsValidAjax() {
		
		$this->writeSession(); // avoid session write timing bug
		$id = $this->params()->fromQuery('id');
		$data = $this->params()->fromQuery('data');
		$requestType = $this->params()->fromQuery('requestType');
		if (! empty($id) && ! empty($data)) {
			// check if user is logged in
			$user = $this->getUser();
			//usleep(200000);
			if (! $user) {
				return $this->output(['status' => false, 'msg' => $this->translate('You must be logged in first')], self::STATUS_NEED_AUTH);
			}
			
			try {
				$catalog = $this->getILS();
				$patron = $this->getILSAuthenticator()->storedCatalogLogin();
				if ($patron) {
					switch ($requestType) {
						case 'ILLRequest' :
							$results = $catalog->checkILLRequestIsValid($id, $data, $patron);
							
							$msg = $results ? $this->translate('ill_request_place_text') : $this->translate('ill_request_error_blocked');
							break;
						case 'StorageRetrievalRequest' :
							$results = $catalog->checkStorageRetrievalRequestIsValid($id, $data, $patron);
							
							$msg = $results ? $this->translate('storage_retrieval_request_place_text') : $this->translate('storage_retrieval_request_error_blocked');
							break;
						default :
							$results = $catalog->checkRequestIsValid($id, $data, $patron);
							
							$msg = $results ? $this->translate('request_place_text') : $this->translate('hold_error_blocked');
							break;
					}
					return $this->output(['status' => $results, 'msg' => $msg], self::STATUS_OK);
				}
			} catch (\Exception $e) {
				// Do nothing -- just fail through to the error message below.
			}
		}
		
		return $this->output($this->translate('An error has occurred'), self::STATUS_ERROR);
	}
}
