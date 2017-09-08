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

// SEE: https://vufind.org/wiki/development:plugins:ils_drivers

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
	 * AKsearch config
	 * 
	 * @var unknown
	 */
	protected $akConfig = null;
	
	/**
	 * Constructor
	 *
	 * @param \VuFind\Date\Converter $dateConverter Date converter
	 */
	public function __construct(\VuFind\Date\Converter $dateConverter, $akConfig = null) {
		$this->dateConverter = $dateConverter;
		$this->akConfig = $akConfig;
	}

	
	/**
	 * {@inheritDoc}
	 * Get API data from Alma.ini file.
	 * 
	 * @see \VuFind\ILS\Driver\DriverInterface::init()
	 */
	public function init() {
		// Get settings
		$this->apiKey = $this->config['API']['key'];
		$this->apiUrl = $this->config['API']['url'];
		$this->debug_enabled = isset($this->config['Catalog']['debug']) ? $this->config['Catalog']['debug'] : false;
	}

	
	/**
	 * {@inheritDoc}
	 * @see \VuFind\ILS\Driver\DriverInterface::getStatus()
	 */
	public function getStatus($id) {
		// TODO: Auto-generated method stub
		/*
		$returnArray = [];
		$status = [];
		$status['id'] = '991063820000541';
		$status['status'] = 'Item Status';
		$status['location'] = 'main';
		$status['reserve'] = 'N';
		$status['callnumber'] = 'B123456 Status';
		$status['availability'] = false;
		$status['use_unknown_message'] = true;
		//$status['services'] = ;
		$returnArray[] = $status;
		return $returnArray;
		*/
	}

	
	/**
	 * {@inheritDoc}
	 * @see \VuFind\ILS\Driver\DriverInterface::getStatuses()
	 */
	public function getStatuses($ids) {
		// TODO: Auto-generated method stub
		/*
		$returnArray = [];
		$returnArray = $this->getStatus('991063820000541');
		return $returnArray;
		*/
	}
	
	
	/**
	 * {@inheritDoc}
	 * @see \VuFind\ILS\Driver\DriverInterface::getHolding()
	 */
	public function getHolding($mmsId, array $patron = null, array $holIds = null) {
				
		// Variable for return value:
		$returnValue = [];
		
		// Max. returned items per holding - defined in AKsearch.ini:
		$maxItemsLoad= ($this->akConfig->MaxItemsLoad->maxItemsLoad) ? $this->akConfig->MaxItemsLoad->maxItemsLoad : 10;
		
		// Get config data:
		$fulfillementUnits = $this->config['FulfillmentUnits'];
		$requestableConfig = $this->config['Requestable'];
		$defaultPolicies = $this->config['DefaultPolicies'];
		
		// Check if we already get holding IDs from the data in Solr. If not, use the API.
		if ($holIds == null) {
			// Get holdings from API as we do not get them from the data in Sorl:
			$holdings = $this->doHTTPRequest($this->apiUrl.'bibs/'.$mmsId.'/holdings?apikey='.$this->apiKey, 'GET');
			// Iterate over holdings and get IDs:
			$holIds = []; // Create empty array
			foreach ($holdings['xml']->holding as $holding) {
				$holIds[] = (string)$holding->holding_id; // Add each ID to the array
			}
		}
		
		// Get items for each holding ID
		$itemsAll = [];
		if (!empty($holIds)) {
			foreach ($holIds as $holId) {
				$itemsForHolding = [];
				$itemList = $this->doHTTPRequest($this->apiUrl.'bibs/'.$mmsId.'/holdings/'.$holId.'/items?limit='.$maxItemsLoad.'&offset=0&apikey='.$this->apiKey, 'GET');
				$totalRecordCount = (string)$itemList['xml']['total_record_count'];

				foreach ($itemList['xml'] as $item) {
					$item->holding_data->addChild('no_of_items', $totalRecordCount); // Add no of total items per holding to the SimpleXMLElement
					$itemsForHolding[] = $item;
				}
				
				// If more items exist and the user want's to display them, page through the items and load them
				if (key_exists('loadAll', $_GET) && $totalRecordCount > $maxItemsLoad) {
					$offset = ($maxItemsLoad > ($totalRecordCount-1)) ? ($totalRecordCount-1) : $maxItemsLoad;
					$itemsForHolding = $this->getMoreItems($mmsId, $holId, $maxItemsLoad, $offset, $totalRecordCount, $itemsForHolding);
				}
				
				$itemsAll = array_merge($itemsAll, $itemsForHolding);
			}
		}
		
		
		// Iterate over items, get available information and add it to an array as described in the VuFind Wiki at:
		// https://vufind.org/wiki/development:plugins:ils_drivers#getholding
		foreach ($itemsAll as $key => $item) {
			$id									= $mmsId;
			$availability						= ((string)$item->item_data->base_status == '1') ? true : false;
			$status								= null; // We calculate the status below based on [DefaultPolicies] in Alma.ini and the item execption status (if set for the item)
			$baseStatus							= (string)$item->item_data->base_status->attributes()->desc; // The Alma base status for the item.
			$libraryCode						= (string)$item->item_data->library;
			$libraryName						= (string)$item->item_data->library->attributes()->desc;
			$locationCode						= (string)$item->item_data->location;
			$locationName						= (string)$item->item_data->location->attributes()->desc;
			$locationHref						= null; // We don't link the location name
			$reserve							= 'N'; // Always N. Is this something like booking or is it "requested"? Befor, we had this code, which lead do misleading notes to the user: ((string)$item->item_data->requested == 'false') ? 'N' : 'Y';
			$callnumber							= (string)$item->holding_data->call_number;
			$duedate							= null; // Additional API call - see below
			$returnDate							= null; // We don't use returnDate
			$number								= $key;
			$requestsPlaced						= null; // We don't use this functionality
			$barcode							= (string)$item->item_data->barcode;
			$public_note						= (string)$item->item_data->public_note; // = Not in item, not holding!
			$notes								= null; // We don't use notes in holdings, just item notes (see above). Deprecated in VuFind 3.0 in favor of holdings_notes
			$holdingsNotes						= null; // // We don't use notes in holdings, just item notes (see above).New in VuFind 3.0
			$item_notes							= ($public_note == null) ? null : [$public_note]; // New in VuFind 3.0
			$summary							= null; // We don't use summary information in holdings.
			$supplements						= null; // We don't use supplement information in holdings.
			$indexes							= null; // We don't use index information in holdings.
			$is_holdable						= false; // Additional checks necessary - see below
			$holdtype							= ''; // Additional checks necessary - see below
			$addLink							= false; // Additional checks necessary - see below
			$item_id							= (string)$item->item_data->pid;
			$holId								= (string)$item->holding_data->holding_id;
			$holdOverride						= false; // We don't override the holds mode
			$addStorageRetrievalRequestLink		= false; // We don't use storage retreival requests at the moment
			$addILLRequestLink					= false; // We don't use ILL requests over AKsearch and Alma at the moment
			$source								= null; // We don't set anything here! If we do, we break the "record -> hold" route in the theme.
			$use_unknown_message				= false;
			$services							= null; // We don't use this functionality
			$description						= (string)$item->item_data->description;
			$callnumber_second					= (string)$item->item_data->alternative_call_number;
			$requested							= ((string)$item->item_data->requested == 'false') ? false : true; //((string)$item->item_data->requested == 'false') ? false : 'Requested';
			$process_type						= (string)$item->item_data->process_type;
			$process_type_desc					= (string)$item->item_data->process_type->attributes()->desc;
			$policyCode							= (string)$item->item_data->policy;
			$policyName							= (string)$item->item_data->policy->attributes()->desc;
			$totalNoOfItems						= (string)$item->holding_data->no_of_items; // This is necessary for paging (load more items)!!!
			
			// Get the fulfillment unit for the item. We need it for some other calculations.
			$itemFulfillmentUnit = $this->getFulfillmentUnitByLocation($locationCode, $fulfillementUnits);
			
			// Check if item is holdable
			$patronGroupCode = $patron['group'];

			if (($itemFulfillmentUnit != null && !empty($itemFulfillmentUnit)) && ($patronGroupCode!= null && !empty($patronGroupCode))) {
				$is_holdable = ($requestableConfig[$itemFulfillmentUnit][$patronGroupCode] == 'Y') ? true : false;
				$holdtype = ($is_holdable) ? 'hold' : '';
				$addLink = ($is_holdable) ? true : false;
			} else {
				//throw new \Exception('Either fulfillment units or user groups are not set correctly in Alma.ini. See sections [FulfillmentUnits] and [Requestable] there and check that all values correspond to existing values in Alma configuration.');
			}
			
			// For some data we need to do additional API calls due to the Alma API architecture
			if ($process_type == 'LOAN') {
				$loanData = $this->doHTTPRequest($this->apiUrl.'bibs/'.$mmsId.'/holdings/'.$holId.'/items/'.$item_id.'/loans?apikey='.$this->apiKey, 'GET');
				$loan = $loanData['xml']->item_loan;
				$duedate = (string)$loan->due_date;
				$duedate = $this->parseDate($duedate);
				$holdtype = ($is_holdable) ? 'reserve' : '';
				$addLink = ($is_holdable) ? true : false;
			}
			
			if ($requested) {
				$duedate = ($duedate == null) ? 'requested' : $duedate;
				$holdtype = ($is_holdable) ? 'reserve' : '';
				$addLink = ($is_holdable) ? true : false;
				$availability = false;
			}
			
			if ($policyCode == null || empty($policyCode)) {
				// There is no exection policy set in the item. We use the default policy (see section [DefaultPolicies]) from Alma.ini
				$status = $defaultPolicies[$itemFulfillmentUnit];
			} else {
				// There is an exceptional item policy set. We use the API to get the description and display it to the user
				$result = $this->doHTTPRequest($this->apiUrl.'conf/code-tables/ItemPolicy?apikey='.$this->apiKey, 'GET');
				$itemPolicies = $result['xml']->rows->row;
				foreach ($itemPolicies as $key => $itemPolicy) {
					$itemPolicyCode = $itemPolicy->code;
					if ($itemPolicyCode == $policyCode) {
						$status = $itemPolicyCode;
						break;
					}
				}
			}
			
			$returnValue[] = [
					// Array fields described on VuFind ILS page:
					'id'								=> $id,
					'availability'						=> $availability,
					'status'							=> $status,
					'location'							=> $locationCode,
					'locationhref'						=> $locationHref,
					'reserve'							=> $reserve,
					'callnumber'						=> $callnumber,
					'duedate'							=> $duedate,
					'returnDate'						=> $returnDate,
					'number'							=> $number,
					'requests_placed'					=> $requestsPlaced,
					'barcode'							=> $barcode,
					'notes'								=> $notes,
					'holdings_notes'					=> $holdingsNotes,
					'item_notes'						=> $item_notes,
					'summary'							=> $summary,
					'supplements'						=> $supplements,
					'indexes'							=> $indexes,
					'is_holdable'						=> $is_holdable,
					'holdtype'							=> $holdtype,
					'addLink'							=> $addLink,
					'item_id'							=> $item_id,
					'holdOverride'						=> $holdOverride,
					'addStorageRetrievalRequestLink'	=> $addStorageRetrievalRequestLink,
					'addILLRequestLink'					=> $addILLRequestLink,
					'source' 							=> $source,
					'use_unknown_message'				=> $use_unknown_message,
					'services'							=> $services,
					
					// Array fields not described in VuFind ILS driver documentation at: https://vufind.org/wiki/development:plugins:ils_drivers#getholding
					'holding_id'						=> $holId,
					'description'						=> $description,
					'callnumber_second'					=> $callnumber_second,
					'libraryCode'						=> $libraryCode,
					'libraryName'						=> $libraryName,
					'locationName'						=> $locationName,
					'baseStatus'						=> $baseStatus,
					'requested'							=> $requested,
					'process_type'						=> $process_type,
					'process_type_desc'					=> $process_type_desc,
					'collection'						=> $locationCode,
					'collection_desc'					=> $locationName,
					'policyCode'						=> $policyCode,
					'policyName'						=> $policyName,
					'totalNoOfItems'					=> $totalNoOfItems, // This is necessary for paging (load more items)!!!
			];
		}
		
		return $returnValue;
	}
	
	
	/**
	 * Check if the "show more items" link/button should be displayed or not.
	 *
	 * @param int $noOfTotalItems	Total number of items that exists for a holding
	 * @return boolean				true if the link/button should be displayed, false otherwise
	 */
	public function showLoadMore($noOfTotalItems) {
		$showLoadMore = false;
		if (!key_exists('loadAll', $_GET)) {
			$noOfItemsToLoad= ($this->akConfig->MaxItemsLoad->maxItemsLoad) ? $this->akConfig->MaxItemsLoad->maxItemsLoad : 10;
			if (strcasecmp($noOfItemsToLoad, 'all') != 0) { // If $noOfItemsToLoad is NOT 'all'
				if ($noOfTotalItems > $noOfItemsToLoad) { // If the holding has more items than there are displayed, show the "load more" link/button.
					$showLoadMore = true;
				}
			}
		}
		return $showLoadMore;
	}
	
	
	private function getMoreItems($mmsId, $holId, $maxItemsLoad, $offset, $totalRecordCount, &$items) {

		$itemList = $this->doHTTPRequest($this->apiUrl.'bibs/'.$mmsId.'/holdings/'.$holId.'/items?limit='.$maxItemsLoad.'&offset='.$offset.'&apikey='.$this->apiKey, 'GET');		
		
		foreach ($itemList['xml'] as $item) {
			$items[] = $item;
		}
		
		if ($totalRecordCount > count($items)) {
			$newOffset = $offset + $maxItemsLoad;
			$newOffset = ($newOffset > ($totalRecordCount-1)) ? ($totalRecordCount-1) : $newOffset;
			$this->getMoreItems($mmsId, $holId, $maxItemsLoad, $newOffset, $totalRecordCount, $items);
		}
		
		return $items;
		
	}

	
	/**
	 * Not implemented at the moment.
	 * 
	 * {@inheritDoc}
	 * @see \VuFind\ILS\Driver\DriverInterface::getPurchaseHistory()
	 */
	public function getPurchaseHistory($id) {
		return []; // Return empty array as we do not implement this functionality yet
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
			throw new ILSException('HTTP server error: '.$statusCode);
		}
		
		/*
		if ($result->isClientError()) {
			throw new ILSException('HTTP client error: '.$statusCode);
		}
		*/
		
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

		$primaryId = $user['id']; // $user['id'] is the username the user used for logging in (normaly this is the barcode). We use it temporarily as user ID to query the user by API.
		
		// Get the patrons details
		$details = $this->doHTTPRequest($this->apiUrl.'users/'.$primaryId.'?&apikey='.$this->apiKey, 'GET');
		$details = $details['xml']; // Get the XML with the patron details of the return-array.
		
		$primaryId = (isset($details->primary_id)) ? (string) $details->primary_id : $user['id']; // Set the user id to the real primary user ID from Alma here
		$address1 = null;
		$address2 = null;
		$address3 = null;
		$address4 = null;
		$city = null;
		$zip = null;
		foreach($details->contact_info->addresses->address as $address) {
			$isPreferredAddress = filter_var((string)$address->attributes()->preferred, FILTER_VALIDATE_BOOLEAN);
			if ($isPreferredAddress) {
				$address1 = (isset($address->line1)) ? (string) $address->line1 : null;
				$address2 = (isset($address->line2)) ? (string) $address->line2 : null;
				$address3 = (isset($address->line3)) ? (string) $address->line3 : null;
				$address4 = (isset($address->line4)) ? (string) $address->line4 : null;
				$address5 = (isset($address->line5)) ? (string) $address->line5 : null;
				$city = (isset($address->city)) ? (string) $address->city : null;
				$zip = (isset($address->postal_code)) ? (string) $address->postal_code : null;
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
		$barcode = (isset($user['barcode'])) ? (string) $user['barcode'] : (string) $user['id'];
		$groupDesc = (isset($details->user_group)) ? (string) $details->user_group->attributes()->desc : null;
		$groupCode = (isset($details->user_group)) ? (string) $details->user_group : null;
		$expiry = (isset($details->expiry_date)) ? (string) $details->expiry_date : null;
		$firstname = (isset($details->first_name)) ? (string) $details->first_name : null;
		$lastname = (isset($details->last_name)) ? (string) $details->last_name : null;
		
		// Set all data required for VuFind (see https://vufind.org/wiki/development:plugins:ils_drivers#getmyprofile)
		// and some additional data for Alma
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
		$recordList['group'] = $groupCode;
		$recordList['groupDesc'] = $groupDesc;
		$recordList['barcode'] = $barcode;
		$recordList['expire'] = $this->parseDate($expiry);
		$recordList['credit'] = $expiry; // FIXME: Get the real credit!
		//$recordList['credit_sum'] = $credit_sum;
		//$recordList['credit_sign'] = $credit_sign;
		$recordList['id'] = $primaryId;
		
		return $recordList;
	}
	
	
	
	public function changeUserData($details) {
		// TODO: Implement this method!
		
		/*
		echo '<strong>Alma -> changeUserData()</strong><br />';
		echo '<pre>';
		print_r($details);
		echo '</pre>';
		*/
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
			$temp = ['id' => $user];
			//$temp['college'] = $this->useradm;			
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
			$groupCode = (isset($details->user_group)) ? (string) $details->user_group : null;

			// VuFind information and some additional information for Alma
			$patron['id'] = (string) $id;
			$patron['barcode'] = ($barcode != null) ? (string) $barcode : (string) $id;
			$patron['firstname'] = (string) $firstName;
			$patron['lastname'] = (string) $lastName;
			$patron['cat_username'] = ($barcode != null) ? (string) $barcode : (string) $id;
			$patron['cat_password'] = $password;
			$patron['email'] = (string) $email_addr;
			$patron['college'] = (string) $college;
			$patron['major'] = null;
			$patron['group'] = $groupCode;
			
			return $patron;
		}
		
		return null;
	}
	
	
	
	/**
	 * Parse a date.
	 *
	 * @param string $date Date to parse
	 *
	 * @return string
	 */
	public function parseDate($date, $withTime = false) {
		
		// Remove trailing Z from end of date (e. g. from Alma we get dates like 2012-07-13Z - without a time, this is wrong): 
		if (strpos($date, 'Z', (strlen($date)-1))) {
			$date = preg_replace('/Z{1}$/', '', $date);
		}
		
		if ($date == null || $date == '') {
			return '';
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
		} else if (preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}$/", substr($date, 0, 19)) === 1) { // added by AK Bibliothek Wien - FOR GERMAN ALMA DATES WITH TIME - Trailing Z is removed above
			// 2017-07-09T18:00:00[Z] - Trailing Z is removed above
			if ($withTime) {
				//2017-06-19T21:59:00[Z] - Trailing Z is removed above
				return $this->dateConverter->convertToDisplayDateAndTime('Y-m-d\TH:i:s', substr($date, 0, 19));
			} else {
				return $this->dateConverter->convertToDisplayDate('Y-m-d', substr($date, 0, 10));
			}
		} else {
			throw new \Exception("Invalid date: $date");
		}
	}
	
	
	/**
	 * Public Function which retrieves renew, hold and cancel settings from the
	 * driver ini file.
	 *
	 * @param string $func   The name of the feature (function or config-section in ILS-Driver .ini-file) to be checked
	 * @param array  $params Optional feature-specific parameters (array)
	 *
	 * @return array An array with key-value pairs.
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function getConfig($func, $params = null) {
		if ($func == 'Holds') {
			// Check if section [Holds] is set in Alma.ini
			if (isset($this->config['Holds'])) {
				return $this->config['Holds'];
			}
			
			// Return default values
			return [
					'HMACKeys' => 'id:holding_id:item_id',
					'extraHoldFields' => 'comments:requiredByDate:pickUpLocation',
					'defaultRequiredDate' => '0:1:0'
			];
		} else {
			return [];
		}
	}

	
	public function placeHold($details) {
		
		$mmsId = $details['id'];
		$holdingId = $details['holding_id'];
		$itemId = $details['item_id'];
		$patronId = $details['patron']['id'];
				
		$pickupLocation = $details['pickUpLocation'];
		if (!$pickupLocation) {
			$pickupLocation = $this->getDefaultPickUpLocation($patron, $details);
		}
		$comment = $details['comment'];
		try {
			$requiredBy = $this->dateConverter->convertFromDisplayDate('Y-m-d', $details['requiredBy']).'Z';
		} catch (DateException $de) {
			return [
					'success'    => false,
					'sysMessage' => 'hold_date_invalid'
			];
		}
		
		$body = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><user_request></user_request>');
		$body->addChild('request_type', 'HOLD');
		$body->addChild('pickup_location_type', 'LIBRARY');
		$body->addChild('pickup_location_library', $pickupLocation);
		$body->addChild('last_interest_date', $requiredBy);
		$body->addChild('comment', $comment);
		$body = $body->asXML();
		
		$result = $this->doHTTPRequest($this->apiUrl.'bibs/'.$mmsId.'/holdings/'.$holdingId.'/items/'.$itemId.'/requests/?user_id='.$patronId.'&apikey='.$this->apiKey, 'POST', $body, ['Content-type' => 'application/xml']);
		
		if ($result) {
			$httpReturnCode = $result['status'];
			
			if ($httpReturnCode == 200) {
				return ['success' => true];
			} else {
				$almaErrorCode = $result['xml']->errorList->error->errorCode;
				$almaErrorMessage = $result['xml']->errorList->error->errorMessage;
				error_log('[Alma] Alma.php -> placeHold(). Error (HTTP code '.$almaReturn['status'].') when placing a Hold in Alma via API: '.$almaErrorMessage);
				
				// TODO: Alma error code 401136 are also user blocks, not only "similar item" error!
				return [
						'success' => false,
						'sysMessage' => 'almaErrorCode'.$almaErrorCode // Translation of sysMessage in language files!
				];
			}
		} else {
			return [
					'success' => false,
					'sysMessage' => "Bestellung konnte nicht abgesendet werden. Hold could not be placed."
			];
		}

	}
	
	
	public function getMyHolds($user) {
		
		$returnArray = [];
		$patronId = $user['id'];
		
		$result = $this->doHTTPRequest($this->apiUrl.'users/'.$patronId.'/requests/?limit=100&apikey='.$this->apiKey, 'GET');
		
		if ($result) {
			$httpReturnCode = $result['status'];
			
			if ($httpReturnCode == 200) {
				$userRequests = $result['xml']->user_request;
				
				foreach ($userRequests as $key => $userRequest) {

					// VuFind specific values (see https://vufind.org/wiki/development:plugins:ils_drivers#getmyholds)
					$requestData['type'] = strtolower((string)$userRequest->request_type);
					$requestData['id'] = (string)$userRequest->mms_id;
					//$requestData['source'] = 'Solr';
					$requestData['location'] = (string)$userRequest->pickup_location_library;
					//$requestData['reqnum'] = ;
					$requestData['expire'] = $this->parseDate((string)$userRequest->expiry_date);
					$requestData['create'] = $this->parseDate((string)$userRequest->request_date);
					$requestData['position'] = (isset($userRequest->place_in_queue)) ? (string)$userRequest->place_in_queue : null;
					$requestData['available'] = ((string)$userRequest->request_status == 'On Hold Shelf') ? true : false ;
					$requestData['item_id'] = (isset($userRequest->item_id)) ? (string)$userRequest->item_id : (string)$userRequest->mms_id;
					//$requestData['volume'] = ;
					//$requestData['publication_year'] = ;
					$requestData['title'] = (string)$userRequest->title;
					//$requestData['isbn'] = ;
					//$requestData['issn'] = ;
					//$requestData['oclc'] = ;
					//$requestData['upc'] = ;
					$requestData['cancel_details'] = (string)$userRequest->id;
					
					// Some extra Alma values
					$requestData['user_primary_id'] = (isset($userRequest->user_primary_id)) ? (string)$userRequest->user_primary_id : null;
					$requestData['request_id'] = (string)$userRequest->request_id;
					$requestData['request_sub_type'] = (isset($userRequest->request_sub_type)) ? (string)$userRequest->request_sub_type : null;
					$requestData['pickup_location'] = (isset($userRequest->pickup_location)) ? (string)$userRequest->pickup_location : null;
					$requestData['pickup_location_type'] = (isset($userRequest->pickup_location_type)) ? (string)$userRequest->pickup_location_type: null;
					$requestData['pickup_location_circulation_desk'] = (isset($userRequest->pickup_location_circulation_desk)) ? (string)$userRequest->pickup_location_circulation_desk: null;
					$requestData['material_type'] = (isset($userRequest->material_type)) ? (string)$userRequest->material_type : null;
					$requestData['barcode'] = (isset($userRequest->barcode)) ? (string)$userRequest->barcode : null;
					
					$returnArray[] = $requestData;
				}
				
				return $returnArray;
				
			}
		}
	}
	
	
	public function cancelHolds($cancelDetails) {
		$returnArray = [];
		$patronId = $cancelDetails['patron']['id'];
		$count = 0;
		
		foreach ($cancelDetails['details'] as $requestId) {
			$item = [];
			// Get some details of the requested items as we need them below. We only can get them from an API request.
			$requestDetails = $this->doHTTPRequest($this->apiUrl.'users/'.$patronId.'/requests/'.$requestId.'/?apikey='.$this->apiKey, 'GET');
			$title= $requestDetails['xml']->title;
			$mmsId = $requestDetails['xml']->mms_id;
			$itemId = (isset($requestDetails['xml']->item_id)) ? (string)$requestDetails['xml']->item_id : (string)$requestDetails['xml']->mms_id;
			
			// Delete the request and get the returned status message
			$apiResult = $this->doHTTPRequest($this->apiUrl.'users/'.$patronId.'/requests/'.$requestId.'/?apikey='.$this->apiKey, 'DELETE');
			$apiResultStatus = $apiResult['status'];
			
			// Check if the cancellation was successful and create an array as described here: https://vufind.org/wiki/development:plugins:ils_drivers#cancelholds
			if ($apiResultStatus == 204) {
				$count = $count + 1;
				$item[$itemId]['success'] = true;
				$item[$itemId]['status'] = 'hold_cancel_success_items';
			} else {
				if (isset($apiResult['xml'])) {
					$almaErrorCode = $apiResult['xml']->errorList->error->errorCode;
					$sysMessage = $apiResult['xml']->errorList->error->errorMessage;
				} else {
					$almaErrorCode = '401652'; // General error code
					$sysMessage = 'HTTP status code: '.$apiResultStatus;
				}
				$item[$itemId]['success'] = false;
				$item[$itemId]['status'] = 'almaErrorCode'.$almaErrorCode; // Translation of this message in language files!
				$item[$itemId]['sysMessage'] = $sysMessage.'. Alma Request ID: '.$requestId;
			}
			
			$returnArray['items'] = $item;
		}
		
		$returnArray['count'] = $count;
		
		return $returnArray;
	}
	
	
	public function getCancelHoldDetails($holdDetails) {
		return $holdDetails['request_id'];
	}
	
	
	public function getMyTransactions($patron) {
		$returnArray = [];
		$patronId = $patron['id'];
		$nowTS = mktime(); // Timestamp respecting the timezone. Use time() for timezone UTC
		
		// Get loans from user via Alma API
		$apiResult = $this->doHTTPRequest($this->apiUrl.'users/'.$patronId.'/loans/?limit=100&order_by=due_date&direction=DESC&expand=renewable&apikey='.$this->apiKey, 'GET');
		
		if ($apiResult) {
			foreach ($apiResult['xml']->item_loan as $itemLoan) {
				
				$loan['duedate'] = $this->parseDate((string)$itemLoan->due_date, true);
				//$loan['dueTime'] = ;
				$loan['dueStatus'] = null; // Calculated below
				$loan['id'] = (string)$itemLoan->mms_id;
				//$loan['source'] = 'Solr';
				$loan['barcode'] = (string)$itemLoan->item_barcode;
				//$loan['renew'] = ;
				//$loan['renewLimit'] = ;
				//$loan['request'] = ;
				//$loan['volume'] = ;
				//$loan['publication_year'] = ;
				$loan['renewable'] = (strtolower((string)$itemLoan->renewable) == 'true') ? true : false;
				//$loan['message'] = ;
				$loan['title'] = (string)$itemLoan->title;
				$loan['item_id'] = (string)$itemLoan->loan_id;
				$loan['institution_name'] = (string)$itemLoan->library;
				//$loan['isbn'] = ;
				//$loan['issn'] = ;
				//$loan['oclc'] = ;
				//$loan['upc'] = ;
				$loan['borrowingLocation'] = (string)$itemLoan->circ_desk;
				
				// Calculate due status
				$dueDateTS = strtotime($loan['duedate']);
				
				if ($nowTS > $dueDateTS) {
					// Loan is overdue
					$loan['dueStatus'] = 'overdue';
				} else if (($dueDateTS - $nowTS) < 86400) {
					// Due date within one day
					$loan['dueStatus'] = 'due';
				}				
				
				$returnArray[] = $loan;
			}
		}
		
		return $returnArray;
	}
	
	
	public function renewMyItems($renewDetails) {
		$returnArray = [];
		$patronId = $renewDetails['patron']['id'];

		foreach ($renewDetails['details'] as $loanId) {
			$renewal = [];
			$apiResult = $this->doHTTPRequest($this->apiUrl.'users/'.$patronId.'/loans/'.$loanId.'/?op=renew&apikey='.$this->apiKey, 'POST');
			$apiResultStatus = $apiResult['status'];
			
			if ($apiResultStatus == 200) {
				$blocks = false;
				$renewal[$loanId]['success'] = true;
				$renewal[$loanId]['new_date'] = $this->parseDate((string)$apiResult['xml']->due_date, true);
				//$renewal[$loanId]['new_time'] = ;
				$renewal[$loanId]['item_id'] = (string)$apiResult['xml']->loan_id;
				$renewal[$loanId]['sysMessage'] = 'renew_success';
				
				$returnArray['details'] = $renewal;
			} else {
				// TODO: Renewals in Alma are possible despite user blocks! This is a known bug in Alma and has to be checked again!
				$blocks[] = 'renew_fail';
			}
		}
		
		$returnArray['blocks'] = $blocks;
		
		return $returnArray;
	}
	
	
	public function getRenewDetails($checkOutDetails) {
		$loanId = $checkOutDetails['item_id'];
		return $loanId;
	}
	
	
	public function getMyFines($patronDetails) {
		$returnArray = [];
		
		$patronId = $patronDetails['id'];
		$apiResult = $this->doHTTPRequest($this->apiUrl.'users/'.$patronId.'/fees/?apikey='.$this->apiKey, 'GET');
		$apiResultStatus = $apiResult['status'];
		
		if ($apiResultStatus == 200) {
			foreach ($apiResult['xml']->fee as $apiFee) {
				$fee = [];
				$fee['amount'] = ((string)$apiFee->original_amount*100); // VuFind uses Pennies/Cent!
				//$fee['checkout'] = ;
				$fee['fine'] = (string)$apiFee->type;
				$fee['balance'] = ((string)$apiFee->balance*100); // VuFind uses Pennies/Cent!
				$fee['createdate'] = $this->parseDate((string)$apiFee->creation_time, true);
				//$fee['duedate'] = ;
				$fee['id'] = null; // Calculated further down
				//$fee['source'] = 'Solr';
				
				if (isset($apiFee->barcode)) {
					$barcodeLink = (string)$apiFee->barcode['link'];
					$itemByBarcode = $this->doHTTPRequest($barcodeLink.'&apikey='.$this->apiKey, 'GET');
					if($itemByBarcode['status'] == 200) {
						$mmsId = (string)$itemByBarcode['xml']->bib_data->mms_id;
						$fee['id'] = $mmsId;
					}
				}

				$returnArray[] = $fee;
			}
		}
		
		return $returnArray;
	}
	
	
	/**
	 * We do this with Solr so we do not use this function. We just return an empty array.
	 */
	public function getNewItems($page, $limit, $daysOld, $fundId = null) {
		return [];
	}
	
	
	/**
	 * FOR FUTURE USE. NOT USED AT THE MOMENT. JUST RETURNING THE DEFAULT PICKUP LOCATION FROM Alma.ini FOR CORRECT REQUEST PLACEMENT.
	 * SEE ALSO SECTION [PickupLocations] in Alma.ini!
	 * 
	 * Creates a drop down if the return array returns 2 or more pickup locations. The use then can choose where he wants to
	 * pick up the item.
	 * 
	 * @param array $patron   Patron information returned by the patronLogin method.
     * @param array $holdInfo Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data. May be used to limit the pickup options
     * or may be ignored. The driver must not add new options to the return array
     * based on this data or other areas of VuFind may behave incorrectly.
     * 
	 * @return array        An array of associative arrays with locationID and locationDisplay keys
	 */
	public function getPickUpLocations($patron, $holdInfo = null) {
		$default = ['locationID' => $this->getDefaultPickUpLocation()];
		$returnArray = (empty($default)) ? [] : [$default];
		return $returnArray;
	}
	
	
	public function getDefaultPickUpLocation() {
		$defaultPickupLocation = (isset($this->config['Holds']['defaultPickUpLocation'])) ? $this->config['Holds']['defaultPickUpLocation'] : false;
		return $defaultPickupLocation;
	}
	
	
	private function getFulfillmentUnitByLocation($locationCode, $fulfillmentUnits) {
		foreach ($fulfillmentUnits as $key => $val) {
			if (array_search($locationCode, $val) !== false) {
				return $key;
			}
		}
		return null;
	}

}
?>