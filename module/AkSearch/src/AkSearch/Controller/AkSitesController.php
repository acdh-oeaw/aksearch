<?php
/**
 * Controller for additional AK sites, e. g. "about" site.
 *
 * PHP version 5
 *
 * Copyright (C) AK Bibliothek Wien 2016.
 * Modified some functions from extended original:
 * @see \VuFind\Controller\AbstractSearch
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
 * @category AkSearch
 * @package  Controller
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://wien.arbeiterkammer.at/service/bibliothek/
 */

namespace AkSearch\Controller;
use VuFind\Controller\AbstractBase;

class AkSitesController extends AbstractBase {
	/**
	 * Call action to go to "about" page.
	 * 
	 * @return \Zend\View\Model\ViewModel
	 */
	public function aboutAction() {
		return $this->createViewModel();
	}
	
	/**
	 * Call action to go to "change user data" page.
	 *
	 * @return \Zend\View\Model\ViewModel
	 */
	public function changeUserDataAction() {
		// This shows the login form if the user is not logged in when in route /AkSites/ChangeUserData:
		if (!$this->getAuthManager()->isLoggedIn()) {
			return $this->forceLogin();
		}
		
		// If not submitted, are we logged in?
		if (!$this->getAuthManager()->supportsPasswordChange()) {
			$this->flashMessenger()->addMessage('recovery_new_disabled', 'error');
			return $this->redirect()->toRoute('home');
		}
		
		// User must be logged in at this point, so we can assume this is non-false:
		$user = $this->getUser();
		
		// Begin building view object:
		$view = $this->createViewModel($this->params()->fromPost());
		
		// Obtain user information from ILS:
		$catalog = $this->getILS();
		$profile = $catalog->getMyProfile($patron);
		
		// Set user information to view object. We can use it in our changeuserdata.phtml file.
		$view->profile = $profile;
		$view->username = $user->username;
		
		// Identification
		$user->updateHash();
		$view->hash = $user->verify_hash;
		$view->setTemplate('aksites/changeuserdata');
		$view->useRecaptcha = $this->recaptcha()->active('changeuserdata');
		
		// If cancel button was clicked, return to home page
		if ($this->formWasSubmitted('cancel')) {
			return $this->redirect()->toRoute('home');
		}
		
		// If form was submitted
		if ($this->formWasSubmitted('submit')) {
			
			// 0. Click button in changeuserdata.phtml
			// 1. AkSitesController.php->changeUserDataAction()
			// 2. Manager.php->updateUserData()
			// 3. ILS.php->updateUserData()
			// 4. Aleph.php->changeUserData();
			try {
				$result = $this->getAuthManager()->updateUserData($this->getRequest());
			} catch (AuthException $e) {
				$this->flashMessenger()->addMessage($e->getMessage(), 'error');
				return $view;
			}
			
			if ($result['success']) {
				// Show message and go to home on success
				$this->flashMessenger()->addMessage('changed_userdata_success', 'success');
				return $this->redirect()->toRoute('myresearch-home');
			} else {
				$this->flashMessenger()->addMessage($result['status'], 'error');
				return $view;
			}

		}
		
		return $view;
	}
}

?>