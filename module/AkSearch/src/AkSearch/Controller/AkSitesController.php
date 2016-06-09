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

class AkSitesController extends AbstractBase
{
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
		if (!is_array($patron = $this->catalogLogin())) {
			return $patron;
		}
		
		// User must be logged in at this point, so we can assume this is non-false:
		$user = $this->getUser();
		
		// Process home library parameter (if present):
		$homeLibrary = $this->params()->fromPost('home_library', false);
		if (!empty($homeLibrary)) {
			$user->changeHomeLibrary($homeLibrary);
			$this->getAuthManager()->updateSession($user);
			$this->flashMessenger()->addMessage('profile_update', 'success');
		}
		
		// Begin building view object:
		$view = $this->createViewModel();
		
		// Obtain user information from ILS:
		$catalog = $this->getILS();
		$profile = $catalog->getMyProfile($patron);
		$profile['home_library'] = $user->home_library;
		$view->profile = $profile;
		try {
			$view->pickup = $catalog->getPickUpLocations($patron);
			$view->defaultPickupLocation = $catalog->getDefaultPickUpLocation($patron);
		} catch (\Exception $e) {
			// Do nothing; if we're unable to load information about pickup
			// locations, they are not supported and we should ignore them.
		}
		
		return $view;
		
		
		//return $this->createViewModel();
	}
}

?>