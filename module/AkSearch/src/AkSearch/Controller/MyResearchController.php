<?php

namespace AkSearch\Controller;

use VuFind\Controller\MyResearchController as DefaultMyResearchController;
use VuFind\Exception\Auth as AuthException;
use Zend\Mail as Mail;
use AkSearch\Controller\Plugin\AkSearch;
use VuFind\I18n\Translator\TranslatorAwareInterface;

class MyResearchController extends DefaultMyResearchController implements TranslatorAwareInterface {

	use \VuFind\I18n\Translator\TranslatorAwareTrait;
	
	
    /**
     * Prepare and direct the home page where it needs to go.
     * 
     * Overriding default home action for changing followup URL
     * if OTP password authentication.
     *
     * @return mixed
     */
    public function homeAction() {
	
        // Process login request, if necessary (either because a form has been
        // submitted or because we're using an external login provider):
        if ($this->params()->fromPost('processLogin') || $this->getSessionInitiator() || $this->params()->fromPost('auth_method') || $this->params()->fromQuery('auth_method')) {
            try {
                if (!$this->getAuthManager()->isLoggedIn()) {
                    $this->getAuthManager()->login($this->getRequest());
                }
            } catch (AuthException $e) {
                $this->processAuthenticationException($e);
            }
        }

        // Not logged in?  Force user to log in:
        if (!$this->getAuthManager()->isLoggedIn()) {
            $this->setFollowupUrlToReferer();
            
            // Clear followup url so that we got to the default page after login. This is important for OTP password action.
            $clearFollowupUrl = filter_var($this->params()->fromQuery('clearFollowupUrl', false), FILTER_VALIDATE_BOOLEAN);
            if ($clearFollowupUrl) {
            	$this->clearFollowupUrl();
            }
            
            return $this->forwardTo('MyResearch', 'Login');
        }
        
        // Logged in?  Forward user to followup action
        // or default action (if no followup provided):
        if ($url = $this->getFollowupUrl()) {
            $this->clearFollowupUrl();

            // If a user clicks on the "Your Account" link, we want to be sure
            // they get to their account rather than being redirected to an old
            // followup URL. We'll use a redirect=0 GET flag to indicate this:
            if ($this->params()->fromQuery('redirect', true)) {
                return $this->redirect()->toUrl($url);
            }
        }

        $page = isset($this->akSearch()->getConfig()->Site->defaultAccountPage) ? $this->akSearch()->getConfig()->Site->defaultAccountPage : 'Favorites';

        // Default to search history if favorites are disabled:
        if ($page == 'Favorites' && !$this->listsEnabled()) {
            return $this->forwardTo('Search', 'History');
        }
        return $this->forwardTo('MyResearch', $page);
    }
    
    
    /**
     * "Create account" action.
     * 
     * Overriding default action for use with Alma.
     *
     * @return mixed
     */
    public function accountAction() {
    	$config = $this->getConfig();
    	$configAlma = $this->getConfig('Alma');
    	
    	// Translator
    	$translator = $this->getServiceLocator()->get('VuFind\Translator');
    	$this->setTranslator($translator);
    	
    	// Set timezone
    	date_default_timezone_set($config->Site->timezone);
    	
    	// Get display date format from config
    	$displayDateFormat = (isset($config->Site->displayDateFormat) ? $config->Site->displayDateFormat : 'm-d-Y');
    	
    	// If the user is already logged in, don't let them create an account:
    	if ($this->getAuthManager()->isLoggedIn()) {
    		return $this->redirect()->toRoute('myresearch-home');
    	}
    	// If authentication mechanism does not support account creation, send the user away!
    	$method = trim($this->params()->fromQuery('auth_method'));
    	if (!$this->getAuthManager()->supportsCreation($method)) {
    		return $this->forwardTo('MyResearch', 'Home');
    	}
    	
    	// We may have come in from a lightbox.  In this case, a prior module
    	// will not have set the followup information.  We should grab the referer
    	// so the user doesn't get lost.
    	// i.e. if there's already a followup url, keep it; otherwise set one.
    	if (!$this->getFollowupUrl()) {
    		$this->setFollowupUrlToReferer();
    	}
    	
    	// Make view
    	$view = $this->createViewModel();
    	
    	// Password policy
    	$passwordPolicy = $this->getAuthManager()->getPasswordPolicy($method);
    	$minPasswordLength = (isset($passwordPolicy['minLength'])) ? $passwordPolicy['minLength'] : null;
    	$maxPasswordLength = (isset($passwordPolicy['maxLength'])) ? $passwordPolicy['maxLength'] : null;
    	$view->passwordPolicy = $passwordPolicy;
    	
    	// Set up reCaptcha
    	$view->useRecaptcha = $this->recaptcha()->active('newAccount');
    	
    	// Pass request to view so we can repopulate user parameters in form:
    	$view->request = $this->getRequest()->getPost();
    	
    	// Pass the jobs from Alma.ini to the view
    	$view->jobs = (isset($configAlma->Users->jobs)) ? $configAlma->Users->jobs->toArray() : array();
    	
    	// Pass the requirede fields array from Alma.ini to the view
    	$requiredFields = (isset($configAlma->Users->newUserRequired)) ? $configAlma->Users->newUserRequired->toArray() : array();
    	$view->required = $requiredFields;
	
    	// Process request, if necessary:
    	if ($this->formWasSubmitted('submit', false)) {
    		
    		// Variable for error checking
    		$formError = false;
    		
    		// Some variables for Alma from the config file
    		$recordType = $configAlma->Users->newUserRecordType;
    		$accountType = $configAlma->Users->newUserAccountType;
    		$status = $configAlma->Users->newUserStatus;
    		$jobCategory = $configAlma->Users->newUserJobCategory;
    		$userGroup = $configAlma->Users->newUserGroup;
    		$emailType = $configAlma->Users->newUserEmailType;
    		$addressType = $configAlma->Users->newUserAddressType;
    		$phoneType = $configAlma->Users->newUserPhoneType;
    		$jobsSpecialEmailText = [];
    		$jobsSpecialEmailTextConfig = (isset($configAlma->Users->jobsSpecialEmailText) ? $configAlma->Users->jobsSpecialEmailText->toArray() : array());
    		if (!empty($jobsSpecialEmailTextConfig)) {
    			foreach ($jobsSpecialEmailTextConfig as $jobSpecialEmailText) {
    				$jobsSpecialEmailText[] = $this->translate($jobSpecialEmailText);
    			}
    		}
    		$jobsSpecialExpiryDate = [];
    		$jobsSpecialExpiryDateConfig = (isset($configAlma->Users->jobsSpecialExpiryDate) ? $configAlma->Users->jobsSpecialExpiryDate->toArray() : array());
    		if (!empty($jobsSpecialExpiryDateConfig)) {
    			foreach ($jobsSpecialExpiryDateConfig as $jobSpecialExpiryDate) {
    				$jobsSpecialExpiryDate[] = $this->translate($jobSpecialExpiryDate);
    			}
    		}
    		
    		// Get values from form
    		$salutation = $this->params()->fromPost('salutation');
    		$firstName = $this->params()->fromPost('firstName');
    		$lastName = $this->params()->fromPost('lastName');
    		$street = $this->params()->fromPost('street');
    		$zip = $this->params()->fromPost('zip');
    		$city = $this->params()->fromPost('city');
    		$email = $this->params()->fromPost('email');
    		$phone = $this->params()->fromPost('phone');
    		$birthday = $this->params()->fromPost('birthday');
    		$birthdayTs = null;
    		if ($birthday != null) {
    			$birthdayTs = strtotime($birthday); // Timestamp of birthday-input
    		}
    		$gender = null;
    		
    		if ($salutation != null) {
    			if ($salutation == $this->translate('salutationMr')) {
    				$gender = 'MALE';
    			} else if ($salutation == $this->translate('salutationMs')) {
    				$gender = 'FEMALE';
    			}
    		}
    		$job = $this->params()->fromPost('job');
    		$password = $this->params()->fromPost('password');
    		$passwordConfirm = $this->params()->fromPost('passwordConfirm');
    		$dataProcessing = ($this->params()->fromPost('dataProcessing')) ? true : false;
    		$houseAndUsageRules = ($this->params()->fromPost('houseAndUsageRules')) ? true : false;
    		$dateToday = date("Y-m-d");
    		$dateExpiry = null;
    		$dateExpiryTS = null;
    		$captchaCode = $this->params()->fromPost('captchaCode');
    		$dateExpiryConfig = (isset($configAlma->Users->expiryDate)) ? $configAlma->Users->expiryDate->toArray() : ['scope' => 'Y', 'add' => 1];
    		$dateExpiryScope = (isset($dateExpiryConfig['scope'])) ? $dateExpiryConfig['scope'] : 'Y';
    		$dateExpiryAdd = (isset($dateExpiryConfig['add'])) ? $dateExpiryConfig['add'] : 1;
    		$jobsDateExpiryConfig = (isset($configAlma->Users->jobsSpecialExpiryDate)) ? $configAlma->Users->jobsSpecialExpiryDate->toArray() : ['scope' => 'Y', 'add' => 1];
    		$jobsDateExpiryScope = (isset($jobsDateExpiryConfig['scope'])) ? $jobsDateExpiryConfig['scope'] : 'Y';
    		$jobsDateExpiryAdd = (isset($jobsDateExpiryConfig['add'])) ? $jobsDateExpiryConfig['add'] : 1;
    		
    		if (in_array($job, $jobsSpecialExpiryDate)) { // Special expiry date for certain job(s)
    			// TODO: Remove tolerance date and VWA date if contributing code to VuFind master code becaus this is a special case for AK Bibliothek Wien.
    			//       Use commented code below instead!
    			$toleranceDateTS = mktime(0, 0, 0, 8, 1, date('Y')); // 1. august of current year
    			$todayDateTS = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
    			if ($todayDateTS > $toleranceDateTS) { // If a user registers after tolerance date, add 1 year to expiry date.
    				$dateExpiryTS = mktime(0, 0, 0, 9, 30, date('Y') + 1);
    				$dateExpiry = date('Y-m-d', $dateExpiryTS);
    			} else {
    				$dateExpiryTS = mktime(0, 0, 0, 9, 30, date('Y'));
    				$dateExpiry = date('Y-m-d', $dateExpiryTS);
    			}
    			
    			// TODO: Use this code if contributing to VuFind master code!
    			/*
    			$dateExpiryTS = mktime(0, 0, 0, date('m') + ((strcasecmp($jobsDateExpiryScope, 'm') == 0) ? $jobsDateExpiryAdd : 0), date('d') + ((strcasecmp($jobsDateExpiryScope, 'd') == 0) ? $jobsDateExpiryAdd : 0), date('Y')  + ((strcasecmp($jobsDateExpiryScope, 'Y') == 0) ? $jobsDateExpiryAdd : 0));
    			$dateExpiry = date('Y-m-d', $dateExpiryTS);
    			*/
    		} else { // Set default expiry date
    			$dateExpiryTS = mktime(0, 0, 0, date('m') + ((strcasecmp($dateExpiryScope, 'm') == 0) ? $dateExpiryAdd : 0), date('d') + ((strcasecmp($dateExpiryScope, 'd') == 0) ? $dateExpiryAdd : 0), date('Y')  + ((strcasecmp($dateExpiryScope, 'Y') == 0) ? $dateExpiryAdd : 0));
    			$dateExpiry = date('Y-m-d', $dateExpiryTS);
    		}

    		$birthdayAlma = ($birthdayTs != null) ? date('Y-m-d', $birthdayTs) : null;
    		
    		// Array for holding error messages:
    		$errorMsg = array();
    		
    		// Check if required values are set:
    		foreach ($requiredFields as $key => $value) {
    			if (${$value} == null) {
    				$errorMsg[] = $this->translate('fieldIsRequired', ['_fieldname_' => $this->translate($value)]);
    				$formError = true;
    				break;
    			}
    		}
    		
    		// Check if eMail address is valid:
    		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    			$errorMsg[] = $this->translate('Email address is invalid');
    			$formError = true;
    		}
    		
    		// Check if password has min 6 signs:
    		if ($minPasswordLength != null && strlen($password) < $minPasswordLength) {
    			$errorMsg[] = $this->translate('password_minimum_length', ['%%minlength%%' => $minPasswordLength]);
    			$formError = true;
    		}
    		
    		// Check if password and password confirm are the same:
    		if ($password != $passwordConfirm) {
    			$errorMsg[] = $this->translate('Passwords do not match');
    			$formError = true;
    		}
    		
    		// Check if captcha code is correct:
    		include_once 'vendor/securimage/securimage.php';
    		$securimage = new \Securimage();
    		if ($securimage->check($captchaCode) == false) {
    			$errorMsg[] = $this->translate('captchaError');
    			$formError = true;
    		}
    		
    		if ($formError) {
    			// Ouput of error messages
    			foreach ($errorMsg as $errorMessage) {
    				$this->flashMessenger()->addMessage($errorMessage, 'error');
    			}
    		} else {
    			
    			// Generate a barcode. We use it as an additional user identifier in Alma.
    			$stringForHash = $firstName . $lastName . $birthdayTs . $email . time();
    			$barcode = $this->akSearch()->generateBarcode($stringForHash);
    			
    			// Set XML string for inserting new patron in Alma. We send this as POST body in an HTTP request
    			$xml_string = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
						<user>
						  <record_type>'.$recordType.'</record_type>
						  <first_name>'.$firstName.'</first_name>
						  <last_name>'.$lastName.'</last_name>
						  <job_category>'.$jobCategory.'</job_category>
						  <job_description>'.$job.'</job_description>
						  <gender>'.$gender.'</gender>
						  <user_group>'.$userGroup.'</user_group>
						  <birth_date>'.$birthdayAlma.'Z</birth_date>
						  <expiry_date>'.$dateExpiry.'Z</expiry_date>
						  <account_type>'.$accountType.'</account_type>
						  <status>'.$status.'</status>
						  <contact_info>
						    <addresses>
						      <address preferred="true">
						        <line1>'.$street.'</line1>
						        <city>'.$city.'</city>
								<postal_code>'.$zip.'</postal_code>
						        <start_date>'.$dateToday.'Z</start_date>
						        <address_types>
						          <address_type>'.$addressType.'</address_type>
						        </address_types>
						      </address>
						    </addresses>
						    <emails>
						      <email preferred="true">
						        <email_address>'.$email.'</email_address>
						        <email_types>
						          <email_type>'.$emailType.'</email_type>
						        </email_types>
						      </email>
						    </emails>
						    <phones>
						      <phone preferred="true">
						        <phone_number>'.$phone.'</phone_number>
						        <phone_types>
						          <phone_type>'.$phoneType.'</phone_type>
						        </phone_types>
						      </phone>
						    </phones>
						  </contact_info>
						  <user_identifiers>
							<user_identifier>
								<id_type>01</id_type>
								<value>'.$barcode.'</value>
							</user_identifier>
						  </user_identifiers>
						</user>';
    			
    			// Remove whitespaces from XML string:
    			$xml_string = preg_replace("/\n/i", "", $xml_string);
    			$xml_string = preg_replace("/>\s*</i", "><", $xml_string);
    			
    			// Create user in Alma
    			$almaReturn = $this->akSearch()->doHTTPRequest($configAlma->API->url.'users/?&apikey='.$configAlma->API->key, 'POST', $xml_string, ['Content-Type' => 'application/xml']);

    			// Check if Alma could create the user correctly
    			$userCreateError = true;
    			
    			if ($almaReturn['status'] == '200') {
    				$almaUser = $almaReturn['xml'];
    				$primaryId = (string)$almaUser->primary_id;
    				
    				// Create user in VuFind 'user' database
    				$isOtp = false;
    				$createIfNotExist = true;
    				$user = $this->akSearch()->createOrUpdateUserInDb($firstName, $lastName, $email, $password, $isOtp, $primaryId, $barcode, $createIfNotExist);
    				if ($user != null) {
    					// SUCCESS
    					$userCreateError = false;
    					$this->flashMessenger()->addMessage('accountAddedSuccessfullyFlash', 'success');
    					
    					// Send eMails
    					$from = $configAlma->Users->emailFrom;
    					$replyTo = $configAlma->Users->emailReplyTo;
    					$toLibrary = $configAlma->Users->emailLibrary;
    					$sentEmailToPatron = $this->sendEmailToNewPatron($gender, $job, $jobsSpecialEmailText, $firstName, $lastName, $barcode, $dateExpiryTS, $displayDateFormat, $email, $from, $replyTo);
    					$sentEmailToLibrary = $this->sendEmailToLibrary($firstName, $lastName, $street, $zip, $city, $phone, $job, $birthday, $gender, $barcode, $dateExpiryTS, $displayDateFormat, $email, $dataProcessing, $houseAndUsageRules, $from, $replyTo, $toLibrary);
    					
    					if ($sentEmailToPatron && $sentEmailToLibrary) {
    						$view->setTemplate('aksites/createsuccess');
    					} else {
    						$this->flashMessenger()->addMessage($this->translate('eMailError'), 'error');
    					}
    				} else {
    					$this->flashMessenger()->addMessage($this->translate('newUserDbError'), 'error');
    				}
    			} else {
    				$this->flashMessenger()->addMessage($this->translate('newUserIlsError'), 'error');
    			}
    		}
    	}
    	
    	return $view;
    }

    
    /**
     * Send eMail to new patron if account was created successfully.
     * 
     * @param string $gender
     * @param string $job
     * @param string $salutation
     * @param string $firstName
     * @param string $lastName
     * @param string $barcode
     * @param int    $dateExpiryTS
     * @param string $email
     * 
     * @return boolean true if eMail was sent successfully, false otherwise
     */
    private function sendEmailToNewPatron($gender, $job, $jobsSpecialEmailText, $firstName, $lastName, $barcode, $dateExpiryTS, $displayDateFormat, $to, $from, $replyTo) {
    	$success = false;
    	$subject= $this->translate('eMailToUserSubject');
    	$salutation = ($gender == 'FEMALE') ? $this->translate('eMailToUserSalutationMs') : $this->translate('eMailToUserSalutationMr');
    	$specialText = '';
    	if (in_array($job, $jobsSpecialEmailText)) {
    		$specialText= $this->translate('eMailToUserSpecialText');
    	}  	
    	$tokens = [
    			'_salutation_' => $salutation,
    			'_firstName_' => $firstName,
    			'_lastName_' => $lastName,
    			'_barcode_' => $barcode,
    			'_dateExpiry_' => date($displayDateFormat, $dateExpiryTS),
    			'_specialText_' => $specialText,
    	];
    	$emailText = $this->translate('eMailToUserText', $tokens);
    	
    	// This sets up the email to be sent
    	$mail = new Mail\Message();
    	$headers = $mail->getHeaders();
    	$headers->removeHeader('Content-Type');
    	$headers->addHeaderLine('Content-Type', 'text/html;charset=UTF-8');
    	$mail->addTo($to);
    	$mail->setFrom($from);
    	$mail->setReplyTo($replyTo);
    	$mail->setSubject($subject);
    	
    	// Prepare HTML for eMail
    	$html = new \Zend\Mime\Part($emailText);
    	$html->type = 'text/html';
    	$html->setCharset('UTF-8');
    	$body = new \Zend\Mime\Message();
    	$body->setParts(array($html));
    	
    	// Add html to eMail body
    	$mail->setBody($body);
    	
    	try {
    		// Send eMail
    		$this->getServiceLocator()->get('VuFind\Mailer')->getTransport()->send($mail);
    		$success = true;
    	} catch (\VuFind\Exception\Mail $mex) {
    		error_log('[Alma] '.$mex->getMessage(). '. Line: '.$mex->getLine());
    	}
    	
    	return $success;
    }

    
    /**
     * Send eMail to library if account was created successfully for a patron.
     * 
     * @param string  $firstName
     * @param string  $lastName
     * @param string  $street
     * @param string  $zip
     * @param string  $city
     * @param string  $phone
     * @param string  $jobText
     * @param string  $birthday
     * @param string  $gender
     * @param string  $barcode
     * @param int     $dateExpiryTS
     * @param string  $email
     * @param boolean $dataProcessing
     * @param boolean $houseAndUsageRules
     * 
     * @return boolean true if eMail was sent successfully, false otherwise
     */
    private function sendEmailToLibrary($firstName, $lastName, $street, $zip, $city, $phone, $job, $birthday, $gender, $barcode, $dateExpiryTS, $displayDateFormat, $email, $dataProcessing, $houseAndUsageRules, $from, $replyTo, $toLibrary) {
    	$success = false;
    	$dateExpiryString = date($displayDateFormat, $dateExpiryTS);
    	$tokens = [
    			'_firstName_' => $firstName,
    			'_lastName_' => $lastName,
    			'_address_' => $street.', '.$zip.' '.$city,
    			'_email_' => $email,
    			'_phone_' => $phone,
    			'_job_' => $job,
    			'_birthday_' => $birthday,
    			'_gender_' => (($gender == 'FEMALE') ? $this->translate('female') : $this->translate('male')),
    			'_barcode_' => $barcode,
    			'_dateExpiry_' => $dateExpiryString,
    			'_acceptedDataProcessing_' => (($dataProcessing == true) ? $this->translate('yes') : $this->translate('no')),
    			'_acceptedHouseAndUsageRules_' => (($houseAndUsageRules == true) ? $this->translate('yes') : $this->translate('no')),
    	];
    	$emailText = $this->translate('eMailToLibraryText', $tokens);
    	$subject = $this->translate('eMailToLibrarySubject', ['_firstName_' => $firstName, '_lastName_' => $lastName]);

    	// This sets up the email to be sent
    	$mail = new Mail\Message();
    	$headers = $mail->getHeaders();
    	$headers->removeHeader('Content-Type');
    	$headers->addHeaderLine('Content-Type', 'text/html;charset=UTF-8');
    	$mail->addTo($toLibrary);
    	$mail->setFrom($from);
    	$mail->setReplyTo($replyTo);
    	$mail->setSubject($subject);
    	
    	// Prepare HTML for eMail
    	$html = new \Zend\Mime\Part($emailText);
    	$html->type = 'text/html';
    	$html->setCharset('UTF-8');
    	$body = new \Zend\Mime\Message();
    	$body->setParts(array($html));
    	
    	// Add html to eMail body
    	$mail->setBody($body);
    	
    	try {
    		// Send eMail
    		$this->getServiceLocator()->get('VuFind\Mailer')->getTransport()->send($mail);
    		$success = true;
    	} catch (\VuFind\Exception\Mail $mex) {
    		error_log('[Alma] '.$mex->getMessage(). '. Line: '.$mex->getLine());
    	}
    	
    	return $success;
    }

}
