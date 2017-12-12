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

		/*
		// Obtain user information from ILS
		$catalog = $this->getILS();
		$profile = $catalog->getMyProfile($patron);
		
		// Get loan history from ILS
		$loanHistory = $catalog->getLoanHistory($profile);
		*/

		$catalog = $this->getILS();
		$profile = $catalog->getMyProfile($patron);		
		$loanHistory = $this->getAuthManager()->getLoanHistory($profile);
		
		// If user has not yet opted-in for loan history, set a value for the template and show an opt-in message there:
		if (isset($loanHistory['isLoanHistory']) && $loanHistory['isLoanHistory'] == false) {
		    $view->loanHistory = $loanHistory;
		    $view->setTemplate('aksites/loanhistory');
		    
		    // Identification
		    $user->updateHash();
		    $view->hash = $user->verify_hash;
		    
		    return $view;
		}		
		
		// If form was submitted, export loan history to CSV
		if ($this->formWasSubmitted('submit')) {
			
			if (isset($loanHistory) && !empty($loanHistory)) {
				// Translator
				$translator = $this->getServiceLocator()->get('VuFind\Translator');
				$this->setTranslator($translator);
				
				// Try to get user operating system
				$ua = $_SERVER['HTTP_USER_AGENT']; // Get the user agent
				$os = 'win'; // Default. Most OS are Windows
				$enc = 'UTF-8'; // Default. In Excel (Windows) we have to use UTF-16LE
				$sep = ','; // Default. In Excel (Windows) we have to use semi-colon ;
				
				if (stripos($ua, 'linux') !== false || stripos($ua, 'CrOS') !== false || stripos($ua, 'BSD') !== false || stripos($ua, 'SunOS') !== false || stripos($ua, 'UNIX') !== false) {
					$os = 'linux';
				} else if (stripos($ua, 'mac') !== false) {
					$os = 'mac';
				} else if (stripos($ua, 'windows') !== false) {
					$os = 'win';
					$enc = 'UTF-16LE';
					$sep = ';';
				}
				
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
					
					$csvTitle = (isset($loanHistoryEntry['title'])) ? $loanHistoryEntry['title'] : '';
					$csvAuthor = (isset($loanHistoryEntry['author'])) ? $loanHistoryEntry['author'] : '';
					$csvPublicationYear = (isset($loanHistoryEntry['publication_year'])) ? $loanHistoryEntry['publication_year'] : '';
					$csvIsbn = (isset($loanHistoryEntry['isbn'][0])) ? $loanHistoryEntry['isbn'][0]: '';
					$csvBarcode = (isset($loanHistoryEntry['barcode'])) ? $loanHistoryEntry['barcode'] : '';
					$csvId = (isset($loanHistoryEntry['id'])) ? $loanHistoryEntry['id'] : '';
					$csvDuedate = (isset($loanHistoryEntry['duedate'])) ? $loanHistoryEntry['duedate'] : '';
					
					$csvTitle = str_replace('"', '', $csvTitle);
					$csvAuthor = str_replace('"', '', $csvAuthor);
					$csvPublicationYear = str_replace('"', '', $csvPublicationYear);
					$csvIsbn = str_replace('"', '', $csvIsbn);
					$csvBarcode = str_replace('"', '', $csvBarcode);
					$csvId = str_replace('"', '', $csvId);
					$csvDuedate = str_replace('"', '', $csvDuedate);

					if ($os == 'win') {
						$csvHeadings['title'] = mb_convert_encoding($this->translate('Title'), 'UTF-16LE', 'UTF-8');
						$csvHeadings['author'] = mb_convert_encoding($this->translate('Author'), 'UTF-16LE', 'UTF-8');
						$csvHeadings['publication_year'] = mb_convert_encoding($this->translate('Year of Publication'), 'UTF-16LE', 'UTF-8');
						$csvHeadings['isbn'] = mb_convert_encoding($this->translate('ISBN'), 'UTF-16LE', 'UTF-8');
						$csvHeadings['barcode'] = mb_convert_encoding($this->translate('Barcode'), 'UTF-16LE', 'UTF-8');
						$csvHeadings['id'] = mb_convert_encoding($this->translate('Identifier'), 'UTF-16LE', 'UTF-8');
						$csvHeadings['duedate'] = mb_convert_encoding($this->translate('Due Date'), 'UTF-16LE', 'UTF-8');
						$csvTitle = mb_convert_encoding($csvTitle, 'UTF-16LE', 'UTF-8');
						$csvAuthor = mb_convert_encoding($csvAuthor, 'UTF-16LE', 'UTF-8');
						$csvPublicationYear = mb_convert_encoding($csvPublicationYear, 'UTF-16LE', 'UTF-8');
						$csvIsbn = mb_convert_encoding($csvIsbn, 'UTF-16LE', 'UTF-8');
						$csvBarcode = str_replace('+', ' +', $csvBarcode);
						$csvBarcode = mb_convert_encoding($csvBarcode, 'UTF-16LE', 'UTF-8');
						$csvId = mb_convert_encoding($csvId, 'UTF-16LE', 'UTF-8');
						$csvDuedate = mb_convert_encoding($csvDuedate, 'UTF-16LE', 'UTF-8');
					}
					
					$csvLoanHistories[$key]['title'] = $csvTitle;
					$csvLoanHistories[$key]['author'] = $csvAuthor;
					$csvLoanHistories[$key]['publication_year'] = $csvPublicationYear;
					$csvLoanHistories[$key]['isbn'] = $csvIsbn;
					$csvLoanHistories[$key]['barcode'] = $csvBarcode;
					$csvLoanHistories[$key]['id'] = $csvId;
					$csvLoanHistories[$key]['duedate'] = $csvDuedate;
				}

				// Add headings to first place of CSV array
				array_unshift($csvLoanHistories, $csvHeadings);
				
				// Create CSV file and add loan history details
				//$bom = "\xEF\xBB\xBF"; // UTF-8 BOM
				$filename = 'ak_loan_history_' . date('d.m.Y') . '.csv';
				header('Content-Disposition: attachment; filename="'.$filename.'"');
				header('Content-Type: text/csv; charset=UTF-16');
				$output = fopen('php://output', 'w');
				//fwrite($output, $bom);
				//fwrite($output, 'sep=,');
				foreach ($csvLoanHistories as $csvLoanHistory) {
					fputcsv($output, array_values($csvLoanHistory), $sep, '"');
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
			// 3. ILS.php/Database.php->updateUserData()
			// 4. Aleph.php/Alma.php->changeUserData();
			try {
				$result = $this->getAuthManager()->updateUserData($this->getRequest());
			} catch (\VuFind\Exception\Auth $e) {
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
	 * Action for "request to set new password" page.
	 *
	 * @return \Zend\View\Model\ViewModel
	 */
	public function requestSetPasswordAction() {
	    // Translator
	    $translator = $this->getServiceLocator()->get('VuFind\Translator');
	    $this->setTranslator($translator);
	    
	    // Get the username
	    $username = $this->getEvent()->getRouteMatch()->getParam('username');
	    $view = $this->createViewModel();
	    
	    // Pass the username to the view so we can show it there
	    $view->username = $username;
	    
	    // Pass request to view so we can repopulate form fields:
	    $view->request = $this->getRequest()->getPost();
	    
	    // Password policy - set a variable that we can use in the template file (setpasswordwithotp.phtml)
	    //$view->passwordPolicy = $this->getAuthManager()->getPasswordPolicy();
	    
	    // If cancel button was clicked, return to home page
	    if ($this->formWasSubmitted('cancel')) {
	        return $this->redirect()->toRoute('home');
	    }
	    
	    // If form was submitted
	    if ($this->formWasSubmitted('submit')) {
	        // 0. Click button in requestsetpassword.phtml
	        // 1. AkSitesController.php->requestSetPasswordAction()
	        // 2. Auth\Manager.php->requestSetPassword()
	        // 3. Auth\Database.php->requestSetPassword()
	        
	        $result = $this->getAuthManager()->requestSetPassword($this->getRequest());
	        
	        if ($result['success']) {
	            $config = $this->getServiceLocator()->get('VuFind\Config')->get('config'); // Get config.ini
	            $almaConfig = $this->getServiceLocator()->get('VuFind\Config')->get('Alma'); // Get Alma.ini
	            $sendEmailResult = $this->sendRequestSetPasswordEmail($result['user'], $config, $almaConfig);
	            if ($sendEmailResult['success']) {
	                $this->flashMessenger()->addMessage($sendEmailResult['status'], 'success');
	            } else {
	                $this->flashMessenger()->addMessage($sendEmailResult['status'], 'success');
	            }
	        } else {
	            $this->flashMessenger()->addMessage($result['status'], 'error');
	            return $view;
	        }
	    }
	    
	    return $view;
	    
	}
	
	
	protected function sendRequestSetPasswordEmail($user, $config, $almaConfig) {
	    $sendEmailResult = [];
	    
	    // If we can't find a user
	    if (null == $user) {
	        $sendEmailResult = array('success' => false, 'status' => 'recovery_user_not_found');
	        //$this->flashMessenger()->addMessage('recovery_user_not_found', 'error');
	    } else {
	        // Make sure we've waiting long enough
	        $hashtime = $this->getHashAge($user->verify_hash);
	        $recoveryInterval = isset($config->Authentication->recover_interval) ? $config->Authentication->recover_interval : 60;
	        if (time() - $hashtime < $recoveryInterval) {
	            //$this->flashMessenger()->addMessage('recovery_too_soon', 'error');
	            $sendEmailResult = array('success' => false, 'status' => 'recovery_too_soon');
	        } else {
	            // Attempt to send the email
	            try {
	                // Create a fresh hash
	                $user->updateHash();
	                $config = $this->getConfig();
	                $renderer = $this->getViewRenderer();
	                $method = $this->getAuthManager()->getAuthMethod();
	                // Custom template for emails (text-only)
	                $message = $renderer->render(
	                    'Email/recover-password.phtml',
	                    [
	                        'library' => $config->Site->title,
	                        'url' => $this->getServerUrl('aksites-setpassword') . '?hash=' . $user->verify_hash . '&auth_method=' . $method
	                    ]
	                );
	                
	                $fromEmail = (isset($almaConfig->Webhook->emailFrom) && !empty($almaConfig->Webhook->emailFrom)) ? $almaConfig->Webhook->emailFrom : $config->Site->email;
	                $this->getServiceLocator()->get('VuFind\Mailer')->send(
	                    $user->email,
	                	$fromEmail,
	                    $this->translate('request_set_password_email_subject'),
	                    $message
	                    );
	                //$this->flashMessenger()->addMessage('recovery_email_sent', 'success');
	                $sendEmailResult = array('success' => true, 'status' => 'success_request_set_password_email');
	            } catch (\Vufind\Exception\Mail $e) {
	                //$this->flashMessenger()->addMessage($e->getMessage(), 'error');
	                $sendEmailResult = array('success' => false, 'status' => $e->getMessage());
	            }
	        }
	    }
	    
	    return $sendEmailResult;
	}
	
	
	/**
	 * Call action to go to "set password" page.
	 *
	 * @return \Zend\View\Model\ViewModel
	 */
	public function setPasswordAction() {
	    $view = $this->createViewModel();
	    
	    // Password policy - set a variable that we can use in the template file (setpasswordwithotp.phtml)
	    $view->passwordPolicy = $this->getAuthManager()->getPasswordPolicy();
	    
	    // TODO - see function "verifyAction" in \VuFind\Controller\MyResearchController->verifyAction()
	    // - DONE: Get the Username by verify_hash (GET-Parameter)
	    // - Show the username
	    // - If form submitted: set password and force_pw_change in DB
	    // - Show success or error message
	    
	    if ($hash = $this->params()->fromQuery('hash')) {
	        $hashtime = $this->getHashAge($hash);
	        $config = $this->getServiceLocator()->get('VuFind\Config')->get('config'); // Get config.ini
	        // Check if hash is expired
	        $hashLifetime = isset($config->Authentication->recover_hash_lifetime) ? $config->Authentication->recover_hash_lifetime : 1209600; // Two weeks
	        if (time() - $hashtime > $hashLifetime) {
	            $this->flashMessenger()->addMessage('recovery_expired_hash', 'error');
	            return $this->forwardTo('AkSites', 'RequestSetPassword');
	        } else {
	            $table = $this->getTable('User');
	            $user = $table->getByVerifyHash($hash);
	            $username = $user->username;
	        }
	    }
	    
	    $view->username = $username;
	    $view->hash = $hash;
	    $view->authMethod = $this->params()->fromQuery('auth_method');
	    
	    // If form was submitted
	    if ($this->formWasSubmitted('submit')) {
	        // 0. Click button in setpassword.phtml
	        // 1. Controller\AkSitesController.php->setPasswordAction()
	        // 2. Auth\Manager.php->setPassword()
	        // 3. Auth\Database.php->setPassword()
	        try {
	        	$result = $this->getAuthManager()->setPassword($view->username, $view->hash, $this->getRequest());
	        } catch (\VuFind\Exception\Auth $e) {
	            $this->flashMessenger()->addMessage($e->getMessage(), 'error');
	            return $view;
	        }
	        
	        if ($result['success']) {
	            // Show message and go to home on success
	            $this->flashMessenger()->addMessage($result['status'], 'success');
	            $view->setTemplate('aksites/setpasswordsuccess');
	            return $view;
	            //return $this->redirect()->toRoute('myresearch-home', array(), array('query' => array('clearFollowupUrl' => '1')));
	        } else {
	        	$this->flashMessenger()->addMessage($result['status'], 'error');
	            return $view;
	        }
	    }
	    
	    
	    return $view;
	}
	
	public function setpasswordsuccessAction() {
		return $this->createViewModel();
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
			} catch (\VuFind\Exception\Auth $e) {
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
	
	
	/**
	 * Helper function for verification hashes.
	 * Taken from \VuFind\Controller\MyResearchController
	 *
	 * @param string $hash User-unique hash string from request
	 *
	 * @return int age in seconds
	 */
	protected function getHashAge($hash) {
	    return intval(substr($hash, -10));
	}

	
}
?>