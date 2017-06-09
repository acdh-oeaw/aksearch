<?php
/**
 * Alma Driver for AKsearch
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
 * @package  ILS Drivers
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://wien.arbeiterkammer.at/service/bibliothek/
 */

namespace AkSearch\ILS\Driver;
use VuFind\ILS\Driver\AbstractBase as AbstractBase;
use VuFind\Exception\ILS as ILSException;


class Alma extends AbstractBase implements \Zend\Log\LoggerAwareInterface, \VuFindHttp\HttpServiceAwareInterface {

	use \VuFind\Log\LoggerAwareTrait;
    use \VuFindHttp\HttpServiceAwareTrait;

	/**
	 * API key of Alma API
	 * 
	 * @var String
	 */
	protected $apiKey;
	
	/**
	 * URL to Alma API
	 *
	 * @var String
	 */
	protected $apiUrl;
	
	/**
	 * Date converter object
	 *
	 * @var \VuFind\Date\Converter
	 */
	protected $dateConverter = null;
	
	
	/**
	 * Constructor
	 *
	 * @param \VuFind\Date\Converter $dateConverter Date converter
	 */
	public function __construct(\VuFind\Date\Converter $dateConverter) {
		$this->dateConverter = $dateConverter;
	}

	/**
	 * {@inheritDoc}
	 * Get API data from Alma.ini file.
	 * 
	 * @see \VuFind\ILS\Driver\DriverInterface::init()
	 */
	public function init() {
		// Get settings
		$this->apiKey = $this->config['APIkey'];
		$this->apiUrl = $this->config['APIurl'];
		$this->debug_enabled = isset($this->config['Catalog']['debug']) ? $this->config['Catalog']['debug'] : false;
	}

	/**
	 * {@inheritDoc}
	 * @see \VuFind\ILS\Driver\DriverInterface::getStatus()
	 */
	public function getStatus($id) {
		// TODO: Auto-generated method stub
	}

	/**
	 * {@inheritDoc}
	 * @see \VuFind\ILS\Driver\DriverInterface::getStatuses()
	 */
	public function getStatuses($ids) {
		// TODO: Auto-generated method stub
	}
	
	/**
	 * {@inheritDoc}
	 * @see \VuFind\ILS\Driver\DriverInterface::getHolding()
	 */
	public function getHolding($mms_id, array $patron = null) {		
		// Variable for return value:
		$returnValue = [];
		
		// Get holdings from API:
		$holdings = $this->doHTTPRequest($this->apiUrl.'bibs/'.$mms_id.'/holdings?apikey='.$this->apiKey, 'GET');
		
		// Iterate over holdings and get IDs:
		$holdingIds = []; // Create empty array
		foreach ($holdings['xml']->holding as $holding) {
			$holdingIds[] = (string)$holding->holding_id; // Add each ID to the array
		}
				
		// Get items for each holding ID
		$items = [];
		if (!empty($holdingIds)) {
			foreach ($holdingIds as $holdingId) {
				$itemList = $this->doHTTPRequest($this->apiUrl.'bibs/'.$mms_id.'/holdings/'.$holdingId.'/items?limit=10&offset=0&apikey='.$this->apiKey, 'GET');
				foreach ($itemList['xml'] as $item) {
					$items[] = $item;
				}
			}
		}
		
		// Iterate over items, get available information and add it to an array as described in the VuFind Wiki at:
		// https://vufind.org/wiki/development:plugins:ils_drivers#getholding
		foreach ($items as $key => $item) {
			$id									= $mms_id;
			$availability						= ((string)$item->item_data->base_status == '1') ? true : false;
			$status								= (string)$item->item_data->base_status->attributes()->desc;
			$location							= (string)$item->item_data->location; // = Location code. Location name would be (string)$item->item_data->location->attributes()->desc
			$reserve							= ((string)$item->item_data->requested == 'false') ? 'N' : 'Y';
			$callnumber							= (string)$item->holding_data->call_number;
			$duedate							= null; // Additional API call - see below
			$returnDate							= false; // We don't use returnDate
			$number								= $key;
			//$requests_placed					= 'string or number';
			$barcode							= (string)$item->item_data->barcode;
			$public_note						= (string)$item->item_data->public_note; // = Not in item, not holding!
			$notes								= null; // We don't use notes in holdings, just item notes (see above). Deprecated in VuFind 3.0 in favor of holdings_notes
			$holdings_notes						= null; // // We don't use notes in holdings, just item notes (see above).New in VuFind 3.0
			$item_notes							= ($public_note == null) ? null : [$public_note]; // New in VuFind 3.0
			//$summary							= array(); // We don't use summary information in holdings.
			//$supplements						= array(); // We don't use supplement information in holdings.
			//$indexes							= array(); // We don't use index information in holdings.
			//$is_holdable						= '???';
			//$holdtype							= '???';
			//$addLink							= '???';
			$item_id							= (string)$item->item_data->pid;
			//$holdOverride						= '???';
			//$addStorageRetrievalRequestLink	= '???';
			//$addILLRequestLink				= '???';
			//$source							= '???';
			$use_unknown_message				= false;
			//$services							= '???'; // Not used as of May 2016
			$callnumber_second					= (string)$item->item_data->alternative_call_number;
			$sub_lib_desc						= (string)$item->item_data->library;
			$requested							= ((string)$item->item_data->requested == 'false') ? false : 'Requested';
			$process_type						= (string)$item->item_data->process_type;
			$process_type_desc					= (string)$item->item_data->process_type->attributes()->desc;
			
			
			// For some data we need to do additional API calls due to the Alma API architecture
			if ($process_type == 'LOAN') {
				$loanData = $this->doHTTPRequest($this->apiUrl.'bibs/'.$mms_id.'/holdings/'.$holdingId.'/items/'.$item_id.'/loans?apikey='.$this->apiKey, 'GET');
				$loan = $loanData['xml']->item_loan;				
				$duedate = (string)$loan->due_date;
				$duedate = $this->parseDate($duedate);
			}
			
			
			$returnValue[] = [
					// Array fields described on VuFind ILS page:
					'id'								=> $id,
					'availability'						=> $availability,
					'status'							=> $status,
					'location'							=> $location,
					'reserve'							=> $reserve,
					'callnumber'						=> $callnumber,
					'duedate'							=> $duedate,
					//'returnDate'						=> 'string',
					'number'							=> $number,
					//'requests_placed'					=> 'string or number',
					'barcode'							=> $barcode,
					'notes'								=> $notes,
					'holdings_notes'					=> $holdings_notes,
					'item_notes'						=> $item_notes,
					//'summary'							=> array(),
					//'supplements'						=> array(),
					//'indexes'							=> array(),
					//'is_holdable'						=> '???',
					//'holdtype'						=> '???',
					//'addLink'							=> '???',
					'item_id'							=> $item_id,
					//'holdOverride'					=> '???',
					//'addStorageRetrievalRequestLink'	=> '???',
					//'addILLRequestLink'				=> '???',
					//'source' 							=> '???',
					'use_unknown_message'				=> $use_unknown_message,
					//'services'						=> '???',
					
					// Array fields not described on VuFind ILS page, but used in existing Aleph driver:
					//'description'						=> 'string',
					//'collection'						=> 'string',
					//'collection_desc'					=> 'string',
					'callnumber_second'					=> $callnumber_second,
					'sub_lib_desc'						=> $sub_lib_desc,
					//'no_of_loans'						=> 'string',
					'requested'							=> $requested,
					
					// Array fields added for Alma:
					'process_type'						=> $process_type,
					'process_type_desc'					=> $process_type_desc,
			];	
		}

		return $returnValue;
	}

	/**
	 * {@inheritDoc}
	 * @see \VuFind\ILS\Driver\DriverInterface::getPurchaseHistory()
	 */
	public function getPurchaseHistory($id) {
		// TODO: Auto-generated method stub
	}
	
	/**
	 * Perform HTTP request.
	 *
	 * @param string $url    URL of request
	 * @param string $method HTTP method
	 *
	 * @return array	xml => SimpleXMLElement, status => HTTP status code
	 */
	protected function doHTTPRequest($url, $method = 'GET', $rawBody = null, $headers = null) {
		if ($this->debug_enabled) {
			$this->debug("URL: '$url'");
		}
			
		$result = null;
		$statusCode = null;
		$returnArray = null;
		
		try {
			$client = $this->httpService->createClient($url);
			$client->setMethod($method);

			if (isset($rawBody)) {
				$client->setRawBody($rawBody);
			}
			
			if (isset($headers)) {
				$client->setHeaders($headers);
			}
			
			$result = $client->send();
			$statusCode = $result->getStatusCode();
		} catch (\Exception $e) {
			throw new ILSException($e->getMessage());
		}
		
		if ($result->isServerError()) {
			throw new ILSException('HTTP error: '.$statusCode);
		}
		
		$answer = $result->getBody();
		if ($this->debug_enabled) {
			$this->debug("url: $url response: $answer (HTTP status code: $statusCode)");
		}
		
		$answer = str_replace('xmlns=', 'ns=', $answer);
		$xml = simplexml_load_string($answer);
		
		if (!$xml && $result->isServerError()) {
			if ($this->debug_enabled) {
				$this->debug("XML is not valid or HTTP error, URL: $url, HTTP status code: $statusCode");
			}
			throw new ILSException("XML is not valid or HTTP error, URL: $url method: $method answer: $answer, HTTP status code: $statusCode.");
		}
		
		$returnArray = ['xml' => $xml, 'status' => $statusCode];
		
		return $returnArray;
	}
	
	
	/**
	 * Get user profile from Alma.
	 *
	 * @param array $user The patron array
	 *
	 * @throws ILSException
	 * @return array      Array of the patron's profile data on success.
	 */
	
	public function getMyProfile($user) {
		
		$primaryId = $user['id'];
		
		// Get the patrons details
		$details = $this->doHTTPRequest($this->apiUrl.'users/'.$primaryId.'?&apikey='.$this->apiKey, 'GET');
		$details = $details['xml']; // Get the XML with the patron details of the return-array.

		$address1 = null;
		$address2 = null;
		$address3 = null;
		$address4 = null;
		$city = null;
		$zip = null;
		foreach($details->contact_info->addresses->address as $address) {
			foreach ($address->attributes() as $name => $value) {
				if ($name == 'preferred' && $value == true) {
					$address1 = (isset($address->line1)) ? (string) $address->line1 : null;
					$address2 = (isset($address->line2)) ? (string) $address->line2 : null;
					$address3 = (isset($address->line3)) ? (string) $address->line3 : null;
					$address4 = (isset($address->line4)) ? (string) $address->line4 : null;
					$address5 = (isset($address->line5)) ? (string) $address->line5 : null;
					$city = (isset($address->city)) ? (string) $address->city : null;
					$zip = (isset($address->postal_code)) ? (string) $address->postal_code : null;
				}
			}
		}
		$email = null;
		$emailsAdditional = [];
		foreach($details->contact_info->emails->email as $emailInfo) {
			foreach ($emailInfo->attributes() as $name => $value) {
				if ($name == 'preferred' && $value == 'true') {
					$email = (isset($emailInfo->email_address)) ? (string) $emailInfo->email_address : null;
				} else if ($name == 'preferred' && $value == 'false') {
					$emailsAdditional[] = (isset($emailInfo->email_address)) ? (string) $emailInfo->email_address : null;
				}
			}
		}
		$phone = null;
		$phonesAdditional = [];
		foreach($details->contact_info->phones->phone as $phoneInfo) {
			foreach ($phoneInfo->attributes() as $name => $value) {
				if ($name == 'preferred' && $value == 'true') {
					$phone = (isset($phoneInfo->phone_number)) ? (string) $phoneInfo->phone_number : null;
				} else if ($name == 'preferred' && $value == 'false') {
					$phonesAdditional[] = (isset($phoneInfo->phone_number)) ? (string) $phoneInfo->phone_number : null;
				}
			}
		}
		$barcode = (isset($user['barcode'])) ? (string) $user['barcode'] : null;
		$group = (isset($details->user_group)) ? (string) $details->user_group->attributes()->desc : null;
		$expiry = (isset($details->expiry_date)) ? (string) $details->expiry_date : null;
		$firstname = (isset($details->first_name)) ? (string) $details->first_name : null;
		$lastname = (isset($details->last_name)) ? (string) $details->last_name : null;
		
		$recordList['firstname'] = $firstname;
		$recordList['lastname'] = $lastname;
		$recordList['address1'] = $address1;
		$recordList['address2'] = $address2;
		$recordList['address3'] = $address3;
		$recordList['address4'] = $address4;
		$recordList['address5'] = $address5;
		$recordList['zip'] = $zip;
		$recordList['city'] = $city;
		$recordList['email'] = $email;
		$recordList['emailsAdditional'] = $emailsAdditional;
		$recordList['phone'] = $phone;
		$recordList['phonesAdditional'] = $phonesAdditional;
		$recordList['group'] = $group;
		$recordList['barcode'] = $barcode;
		$recordList['expire'] = $this->parseDate($expiry);
		$recordList['credit'] = $expiry;
		$recordList['credit_sum'] = $credit_sum;
		$recordList['credit_sign'] = $credit_sign;
		$recordList['id'] = $id;
		
		return $recordList;
	}
	
	
	/**
	 * Change password
	 * If a function with the name "changePassword" exists and option "change_password" in config.ini is set to "true",
	 * a menu item that leads to a form for changing the pasword will appear on the user account page of a logged in user.
	 *
	 * @param array $details An array of patron id and old and new password:
	 * 'patron'      The patron array from patronLogin
	 * 'oldPassword' Old password
	 * 'newPassword' New password
	 *
	 * @return array An array of data on the request including
	 * whether or not it was successful and a system message (if available)
	 *
	 */
	public function changePassword($details) {
		// 0. Click button in newpassword.phtml
		// 1. \VuFind\Controller\MyResearchController.php->newPasswordAction()
		// 2. \VuFind\Auth\Manager.php->updatePassword()
		// 3. \VuFind\Auth\ILS.php->updatePassword()
		// 4. Alma.php->changePassword();
		
		$statusMessage = 'Changing password not successful!';
		$success = false;
		
		$patron = $details['patron'];
		$primaryId = $patron['id'];
		$barcode = trim(htmlspecialchars($patron['barcode'], ENT_COMPAT, 'UTF-8'));
		$catPassword = trim(htmlspecialchars($patron['cat_password'], ENT_COMPAT, 'UTF-8'));
		$oldPassword = trim(htmlspecialchars($details['oldPassword'], ENT_COMPAT, 'UTF-8'));
		$newPassword = trim(htmlspecialchars($details['newPassword'], ENT_COMPAT, 'UTF-8'));
		
		if ($catPassword == $oldPassword) {
			if (strlen($newPassword) >= 4) {
				
				// Get the alma user XML object from API
				$almaUserObject = $this->doHTTPRequest($this->apiUrl.'users/'.$primaryId.'?&apikey='.$this->apiKey, 'GET');
				$almaUserObject = $almaUserObject['xml']; // Get the user XML object from the return-array.
				
				// Remove user roles (they are not touched)
				unset($almaUserObject->user_roles);
				
				// Set new password to XML
				$almaUserObject->password = $newPassword;
				
				// Get XML for update process via API
				$almaUserObjectForUpdate = $almaUserObject->asXML();
				
				// Send update via HTTP PUT
				$updateResult = $this->doHTTPRequest($this->apiUrl.'users/'.$primaryId.'?user_id_type=all_unique&apikey='.$this->apiKey, 'PUT', $almaUserObjectForUpdate, ['Content-type' => 'application/xml']);
				
				if ($updateResult['status'] == '200') {
					$statusMessage = 'Changed password';
					$success = true;
				} else {
					$statusMessage = 'Changing password not successful! HTTP error code: '.$updateResult['status'];
				}
				/*
				echo '<pre>';
				print_r($updateResult);
				echo '</pre>';
				*/
			} else {
    			$statusMessage = 'Minimum lenght of password is 4 characters';
    			$success = false;
    		}
		} else {
    		$statusMessage = 'Old password is wrong';
    		$success = false;
    	}
    	
    	$returnArray = array('success' => $success, 'status' => $statusMessage);
    	
    	return $returnArray;
	}
	
	
	/**
	 * Patron Login
	 *
	 * This is responsible for authenticating a patron against Alma.
	 *
	 * @param string $user     The patron username
	 * @param string $password The patron's password
	 *
	 * @throws ILSException
	 * @return mixed          Associative array of patron info on successful login, null on unsuccessful login.
	 */
	public function patronLogin($user, $password) {
		
		$patron = null;
		$authSuccess = false;
		
		if ($password == null) {
			$temp = ["id" => $user];
			$temp['college'] = $this->useradm;
			return $this->getMyProfile($temp);
		}
		
		try {
			$result = $this->doHTTPRequest($this->apiUrl.'users/'.$user.'?user_id_type=all_unique&op=auth&password='.$password.'&apikey='.$this->apiKey, 'POST');
		} catch (\Exception $ex) {
			throw new ILSException($ex->getMessage());
		}
		
		
		if ($result['status'] == 204) {
			$authSuccess = true;
		} else {
			return null; // Show message for wrong user credentials
		}

		// We got this far, the user must be a valid user.
		if ($authSuccess) {

			$patron = [];
			
			// Get the patrons details
			$details = $this->doHTTPRequest($this->apiUrl.'users/'.$user.'?&apikey='.$this->apiKey, 'GET');
			$details = $details['xml']; // Get the XML with the patron details of the return-array.

			$firstName = (isset($details->first_name)) ? $details->first_name : null;
			$lastName = (isset($details->last_name)) ? $details->last_name : null;
			$email_addr = null;
			foreach($details->contact_info->emails->email as $email) {
				foreach ($email->attributes() as $name => $value) {
					if ($name == 'preferred' && $value == true) {
						$email_addr = (isset($email->email_address)) ? $email->email_address : null;
					}
				}
			}
			$id = $details->primary_id;
			$barcode = null;
			foreach ($details->user_identifiers->user_identifier as $user_identifier) {
				if ($user_identifier->id_type == 'BARCODE') {
					$barcode = (isset($user_identifier->value)) ? $user_identifier->value : null;
				}
			}
			$college = (isset($details->campus_code)) ? $details->campus_code : null;

			$patron['id'] = (string) $id;
			$patron['barcode'] = ($barcode != null) ? (string) $barcode : (string) $id;
			$patron['firstname'] = (string) $firstName;
			$patron['lastname'] = (string) $lastName;
			$patron['cat_username'] = ($barcode != null) ? (string) $barcode : (string) $id;
			$patron['cat_password'] = $password;
			$patron['email'] = (string) $email_addr;
			$patron['college'] = (string) $college;
			$patron['major'] = null;
			
			return $patron;
		}
		
		return null;
	}
	
	/**
	 * Get the sublibrary name by sublibrary code.
	 *
	 * @param string $subLibCode
	 * 			Sublibrary code
	 *
	 * @return string
	 */
	public function getSubLibName($subLibCode) {
		return null;
	}
	
	/**
	 * Parse a date.
	 *
	 * @param string $date Date to parse
	 *
	 * @return string
	 */
	public function parseDate($date) {
		
		// Remove trailing Z from end of date (e. g. from Alma we get dates like 2012-07-13Z - without a time, this is wrong): 
		if (strpos($date, 'Z', (strlen($date)-1))) {
			$date = preg_replace('/Z{1}$/', '', $date);
		}
		
		if ($date == null || $date == "") {
			return "";
		} else if (preg_match("/^[0-9]{8}$/", $date) === 1) { // 20120725
			return $this->dateConverter->convertToDisplayDate('Ynd', $date);
		} else if (preg_match("/^[0-9]+\/[A-Za-z]{3}\/[0-9]{4}$/", $date) === 1) {
			// 13/jan/2012
			return $this->dateConverter->convertToDisplayDate('d/M/Y', $date);
		} else if (preg_match("/^[0-9]+\/[0-9]+\/[0-9]{4}$/", $date) === 1) {
			// 13/7/2012
			return $this->dateConverter->convertToDisplayDate('d/m/Y', $date);
		} else if (preg_match("/^[0-9]{1,2}\/[0-9]{1,2}\/[0-9]{2,4}$/", $date) === 1) { // added by AK Bibliothek Wien - FOR GERMAN ALEPH DATES
			// 13/07/2012
			return $this->dateConverter->convertToDisplayDate('d/m/y', $date);
		} else if (preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/", $date) === 1) { // added by AK Bibliothek Wien - FOR GERMAN ALMA DATES WITHOUT TIME - Trailing Z is removed above
			// 2012-07-13[Z] - Trailing Z is removed above
			return $this->dateConverter->convertToDisplayDate('Y-m-d', $date);
		} else if (preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}$/", $date) === 1) { // added by AK Bibliothek Wien - FOR GERMAN ALMA DATES WITH TIME - Trailing Z is removed above
			// 2017-07-09T18:00:00[Z] - Trailing Z is removed above
			return $this->dateConverter->convertToDisplayDate('Y-m-d', substr($date, 0, 10));
		} else {
			throw new \Exception("Invalid date: $date");
		}
	}
	
	
}
?>