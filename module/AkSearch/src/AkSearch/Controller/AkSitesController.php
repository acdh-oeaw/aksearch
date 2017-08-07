<?php
/**
 * Controller for additional AK sites, e. g. "about" site, "change user data" site, etc.
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

class AkSitesController extends AbstractBase implements \VuFind\I18n\Translator\TranslatorAwareInterface {
	
	use \VuFind\I18n\Translator\TranslatorAwareTrait;
	
	
	/**
	 * Call action to go to "about" page.
	 * 
	 * @return \Zend\View\Model\ViewModel
	 */
	public function aboutAction() {
		return $this->createViewModel();
	}
	
	
	/**
	 * Call action to go to "dataprivacystatement" page.
	 *
	 * @return \Zend\View\Model\ViewModel
	 */
	public function dataPrivacyStatementAction() {
		$view = $this->createViewModel();
		
		// Get AKsearch.ini 
		$akConfig = $this->getServiceLocator()->get('VuFind\Config')->get('AKsearch');
		
		// Check in AKsearch.ini if the Piwik paragraph should be displayed on the data privacy statement site.
		$showPiwikParagraph = $akConfig->DataPrivacyStatement->showPiwikParagraph;
		
		// Set a variable that we can use in the template file (dataprivacystatement.phtml)
		$view->showPiwikParagraph = ($showPiwikParagraph != null && isset($showPiwikParagraph)) ? $showPiwikParagraph : false;
		
		// Return the view to display the page
		return $view;
	}
	
		
	public function loanHistoryAction() {
		// This shows the login form if the user is not logged in when in route /AkSites/LoanHistory
		if (!$this->getAuthManager()->isLoggedIn()) {
			return $this->forceLogin();
		}
		
		// If not submitted, are we logged in?
		if (!$this->getAuthManager()->supportsLoanHistory()) {
			$this->flashMessenger()->addMessage('loanHistoryDisabled', 'error');
			return $this->redirect()->toRoute('home');
		}
		
		// Stop now if the user does not have valid catalog credentials available
		if (!is_array($patron = $this->catalogLogin())) {
			return $patron;
		}
		
		// User must be logged in at this point, so we can assume this is non-false
		$user = $this->getUser();
		
		// Begin building view object
		$view = $this->createViewModel();

		// Obtain user information from ILS
		$catalog = $this->getILS();
		$profile = $catalog->getMyProfile($patron);
		
		// Get loan history from ILS
		$loanHistory = $catalog->getLoanHistory($profile);
		
		// If form was submitted, export loan history to CSV
		if ($this->formWasSubmitted('submit')) {
			
			if (isset($loanHistory) && !empty($loanHistory)) {
				// Translator
				$translator = $this->getServiceLocator()->get('VuFind\Translator');
				$this->setTranslator($translator);
				
				// Translated CSV columns headings
				$csvHeadings = [];
				$csvHeadings['title'] = $this->translate('Title');
				$csvHeadings['author'] = $this->translate('Author');
				$csvHeadings['publication_year'] = $this->translate('Year of Publication');
				$csvHeadings['isbn'] = $this->translate('ISBN');
				$csvHeadings['barcode'] = $this->translate('Barcode');
				$csvHeadings['id'] = $this->translate('Identifier');
				$csvHeadings['duedate'] = $this->translate('Due Date');

				// Loan history values
				$csvLoanHistories = [];
				foreach ($loanHistory as $key => $loanHistoryEntry) {
					$csvLoanHistories[$key]['title'] = (isset($loanHistoryEntry['title'])) ? $loanHistoryEntry['title'] : null;
					$csvLoanHistories[$key]['author'] = (isset($loanHistoryEntry['author'])) ? $loanHistoryEntry['author'] : null;
					$csvLoanHistories[$key]['publication_year'] = (isset($loanHistoryEntry['publication_year'])) ? $loanHistoryEntry['publication_year'] : null;
					$csvLoanHistories[$key]['isbn'] = (isset($loanHistoryEntry['isbn'][0])) ? $loanHistoryEntry['isbn'][0] : null;
					$csvLoanHistories[$key]['barcode'] = (isset($loanHistoryEntry['barcode'])) ? $loanHistoryEntry['barcode'] : null;
					$csvLoanHistories[$key]['id'] = (isset($loanHistoryEntry['id'])) ? $loanHistoryEntry['id'] : null;
					$csvLoanHistories[$key]['duedate'] = (isset($loanHistoryEntry['duedate'])) ? $loanHistoryEntry['duedate'] : null;
				}

				// Add headings to first place of CSV array
				array_unshift($csvLoanHistories, $csvHeadings);
				
				// Create CSV file and add loan history details
				$filename = 'ak_loan_history_' . date('d.m.Y') . '.csv';
				header('Content-Disposition: attachment; filename="'.$filename.'"');
				header('Content-Type: text/csv');
				$output = fopen('php://output', 'w');
				foreach ($csvLoanHistories as $csvLoanHistory) {
					fputcsv($output, array_values($csvLoanHistory), ',', '"');
				}
				fclose($output);
				exit;
			}
			
		}
		
		// Build paginator if needed
		$config = $this->getConfig();
		$limit = isset($config->Catalog->checked_out_page_size) ? $config->Catalog->checked_out_page_size : 50;
		if ($limit > 0 && $limit < count($loanHistory)) {
			$adapter = new \Zend\Paginator\Adapter\ArrayAdapter($loanHistory);
			$paginator = new \Zend\Paginator\Paginator($adapter);
			$paginator->setItemCountPerPage($limit);
			$paginator->setCurrentPageNumber($this->params()->fromQuery('page', 1));
			$pageStart = $paginator->getAbsoluteItemNumber(1) - 1;
			$pageEnd = $paginator->getAbsoluteItemNumber($limit) - 1;
		} else {
			$paginator = false;
			$pageStart = 0;
			$pageEnd = count($loanHistory);
		}
		
		$view->paginator = $paginator;

		foreach ($loanHistory as $i => $current) {					
			// Build record driver (only for the current visible page):
			if ($i >= $pageStart && $i <= $pageEnd) {
				$transactionHistory[] = $this->getDriverForILSRecord($current);
			}
		}
		
		// Set loan history to view
		$view->loanHistory = $transactionHistory;	
		
		// Identification
		$user->updateHash();
		$view->hash = $user->verify_hash;
		$view->setTemplate('aksites/loanhistory');

		return $view;
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
		if (!$this->getAuthManager()->supportsUserDataChange()) {
			$this->flashMessenger()->addMessage('change_userdata_disabled', 'error');
			return $this->redirect()->toRoute('home');
		}
		
		// Stop now if the user does not have valid catalog credentials available:
		if (!is_array($patron = $this->catalogLogin())) {
			return $patron;
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
		$view->auth_method = $this->getAuthManager()->getAuthMethod();
		
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
				return $this->redirect()->toRoute('aksites-changeuserdata');
			} else {
				$this->flashMessenger()->addMessage($result['status'], 'error');
				return $view;
			}
		}
		
		return $view;
	}
	
	
	/**
	 * Call action to go to "set password with one-time-password" page.
	 *
	 * @return \Zend\View\Model\ViewModel
	 */
	public function setPasswordWithOtpAction() {
		$view = $this->createViewModel();
		
		// Password policy - set a variable that we can use in the template file (setpasswordwithotp.phtml)
		$view->passwordPolicy = $this->getAuthManager()->getPasswordPolicy();
		
		// Use re-captcha - set a variable that we can use in the template file (setpasswordwithotp.phtml)
		$view->useRecaptcha = $this->recaptcha()->active('setPasswordWithOtp');
		
		// If cancel button was clicked, return to home page
		if ($this->formWasSubmitted('cancel')) {
			return $this->redirect()->toRoute('home');
		}
		
		// If form was submitted
		if ($this->formWasSubmitted('submit')) {
			// 0. Click button in setpasswordwithotp.phtml
			// 1. AkSitesController.php->setPasswordWithOtpAction()
			// 2. Manager.php->setPasswordWithOtp()
			// 3. Database.php->setPasswordWithOtp()
			try {
				$result = $this->getAuthManager()->setPasswordWithOtp($this->getRequest());
			} catch (AuthException $e) {
				$this->flashMessenger()->addMessage($e->getMessage(), 'error');
				return $view;
			}
			
			if ($result['success']) {
				// Show message and go to home on success
				$this->flashMessenger()->addMessage($result['status'], 'success');
				return $this->redirect()->toRoute('myresearch-home', array(), array('query' => array('clearFollowupUrl' => '1')));
			} else {
				$this->flashMessenger()->addMessage($result['status'], 'error');
				return $view;
			}
		}
		
		// Return the view to display the page
		return $view;
	}
	
	
	/**
	 * Generates the captcha image for the "Register as new user" form.
	 * It executes at URL http[s]://[vufind_url]/AkSites/Captcha
	 */
	public function captchaAction() {	
		require_once 'vendor/securimage/securimage.php';
		$securImage = new \Securimage();
		
		// Setting width and calculating optimal height
		$securImage->image_width = 200;
		$securImage->image_height = (int)($securImage->image_width * 0.35);
		$securImage->perturbation = .5;
		$securImage->num_lines = 2;
		$securImage->show();
	}
	
	
	public function createsuccessAction() {
		return $this->createViewModel();
	}
	
	
	/**
	 * Get a record driver object corresponding to an array returned by an ILS
	 * driver's getMyHolds / getMyTransactions / loanHistory method.
	 * 
	 * This was taken from MyResearchController
	 *
	 * @param array $current Record information
	 *
	 * @return \VuFind\RecordDriver\AbstractBase
	 */
	protected function getDriverForILSRecord($current) {
		$id = isset($current['id']) ? $current['id'] : null;
		$source = isset($current['source']) ? $current['source'] : 'VuFind';
		$record = $this->getServiceLocator()->get('VuFind\RecordLoader')->load($id, $source, true);
		$record->setExtraDetail('ils_details', $current);
		return $record;
	}

	
}
?>