<?php
/**
 * Aleph Driver for AkSearch
 *
 * PHP version 5
 *
 * Copyright (C) AK Bibliothek Wien 2016.
 * Some functions modified by AK Bibliothek Wien, original by: UB/FU Berlin (see VuFind\ILS\Driver\Aleph)
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
 * @package  ILS Drivers
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://wien.arbeiterkammer.at/service/bibliothek/
 */
 
namespace AkSearch\ILS\Driver;

use DateTime;
use VuFind\Exception\Auth as AuthException;
use VuFind\Exception\ILS as ILSException;
use VuFind\ILS\Driver\Aleph as AlephDefault;
use VuFind\ILS\Driver\AlephRestfulException as AlephRestfulExceptionDefault;
use VuFind\ILS\Driver\AlephTranslator as AlephTranslatorDefault;


class Aleph extends AlephDefault {

	protected $akConfig = null;
	
	protected $akHttpRequestRetries = 0;
	
	protected $akHttpRequestMaxRetries = 3;
	
	/**
	 * Constructor
	 *
	 * @param \VuFind\Date\Converter $dateConverter Date converter
	 * @param \VuFind\Cache\Manager  $cacheManager  Cache manager (optional)
	 * @param \Zend\Config\Config    $akConfig      Contents of AKconfig.ini
	 */
	public function __construct(\VuFind\Date\Converter $dateConverter, \VuFind\Cache\Manager $cacheManager = null, $akConfig = null) {
				$this->dateConverter = $dateConverter;
				$this->cacheManager = $cacheManager;
				$this->akConfig = $akConfig;
	}
	
	
	/**
	 * Perform an HTTP request.
	 *
	 * Original by: UB/FU Berlin (see VuFind\ILS\Driver\Aleph)
	 * Modified by AK Bibliothek Wien (Michael Birkner) (increased timeout)
	 *
	 * @param string $url    URL of request
	 * @param string $method HTTP method
	 * @param string $body   HTTP body (null for none)
	 *
	 * @return SimpleXMLElement
	 */
	protected function doHTTPRequest($url, $method = 'GET', $body = null) {
		//echo 'Send HTTP request ' . date('h:i:s') . '<br>';
		//usleep(200000); // Pausing for some microseconds to avoid errors caused by too many API-Calls at the same time
		$this->akHttpRequestRetries++;

		if ($this->debug_enabled) {
			$this->debug("URL: '$url'");
		}
	
		$result = null;
		try {
			$client = $this->httpService->createClient($url);
			$client->setOptions(array('timeout'=>30)); // Increase read timeout because requesting Aleph APIs could take quite a while
			$client->setMethod($method);
			if ($body != null) {
				$client->setRawBody($body);
			}
			$result = $client->send();
		} catch (\Exception $e) {
			throw new ILSException($e->getMessage());
		}
		if (!$result->isSuccess()) {
			throw new ILSException('HTTP error');
		}
		$answer = $result->getBody();
		if ($this->debug_enabled) {
			$this->debug("url: $url response: $answer");
		}
		$answer = str_replace('xmlns=', 'ns=', $answer);
		$result = simplexml_load_string($answer);		
		
		if ($result === null || empty($result)) {
			// Retry max 3 times to get a result
			if ($this->akHttpRequestRetries <= $this->akHttpRequestMaxRetries) {
				$result = $this->doHTTPRequest($url, $method, $body);
			} else {
				if ($this->debug_enabled) {
					$this->debug("XML is not valid, URL: $url");
				}
				throw new ILSException("XML is not valid, URL: $url, method: $method, answer: $answer.");
			}		
		} else {
			$this->akHttpRequestRetries = 0;
			return $result;
		}
		return $result;
	}
	
	
	/**
	 * Perform an XServer request.
	 * 
	 * Original by: UB/FU Berlin (see VuFind\ILS\Driver\Aleph)
	 * Modified by AK Bibliothek Wien (Michael Birkner): Changed to https
	 * 
	 * @param string $op
	 *        	Operation
	 * @param array $params
	 *        	Parameters
	 * @param bool $auth
	 *        	Include authentication?
	 *        	
	 * @return SimpleXMLElement
	 */
	protected function doXRequest($op, $params, $auth = false) {

		if (! $this->xserver_enabled) {
			throw new \Exception('Call to doXRequest without X-Server configuration in Aleph.ini');
		}
		// Changed to https (original is with http)
		$url = "https://$this->host/X?op=$op";
		$url = $this->appendQueryString($url, $params);
		if ($auth) {
			$url = $this->appendQueryString($url, array('user_name' => $this->wwwuser, 'user_password' => $this->wwwpasswd));
		}
		
		$result = $this->doHTTPRequest($url);
		if ($result->error && $result->error != 'empty set' && strpos($result-error, 'Succeeded to REWRITE table z303', 0) !== false) { // Excluding "empty set" prevents error message for empty "getNewItems" result
			if ($this->debug_enabled) {
				$this->debug("XServer error, URL is $url, error message: $result->error");
			}
			throw new ILSException("XServer error: $result->error");
		}
		return $result;
	}
	

	/**
	 * Perform a RESTful DLF request.
	 * 
	 * Original by: UB/FU Berlin (see VuFind\ILS\Driver\Aleph)
	 * Modified by AK Bibliothek Wien (Michael Birkner): Changed to https
	 * 
	 * @param array $path_elements
	 *        	URL path elements
	 * @param array $params
	 *        	GET parameters (null for none)
	 * @param string $method
	 *        	HTTP method
	 * @param string $body
	 *        	HTTP body
	 *        	
	 * @return SimpleXMLElement
	 */
	protected function doRestDLFRequest($path_elements, $params = null, $method = 'GET', $body = null) {		
		$path = '';
		foreach ($path_elements as $path_element) {
			$path .= $path_element . "/";
		}
		
		// Changed to https (original is with http) and removed port (not used at AK Bibliothek Wien)
		$url = "https://$this->host/rest-dlf/" . $path;
		$url = $this->appendQueryString($url, $params);
		
		$result = $this->doHTTPRequest($url, $method, $body);
		$replyCode = (string) $result->{'reply-code'};
		if ($replyCode != "0000") {
			$replyCode = (!$replyCode) ? '0' : $replyCode;
			$replyText = (string) $result->{'reply-text'};
			$this->logger->err("DLF request failed", array('url' => $url, 'reply-code' => $replyCode, 'reply-message' => $replyText));
			$ex = new AlephRestfulExceptionDefault($replyText, $replyCode);
			$ex->setXmlResponse($result);
			throw $ex;
		}
		return $result;
	}

	
	/**
	 * Get Holding
	 *
	 * This is responsible for retrieving the holding information of a certain
	 * record.
	 *
	 * Original by: UB/FU Berlin (see VuFind\ILS\Driver\Aleph)
	 * Modified by AK Bibliothek Wien (Michael Birkner): Changed date format for german Aleph
	 *
	 * @param string $id
	 *        	The record id to retrieve the holdings for
	 * @param array $patron
	 *        	Patron data
	 *        	
	 * @throws \VuFind\Exception\Date
	 * @throws ILSException
	 * @return array On success, an associative array with the following
	 *         keys: id, availability (boolean), status, location, reserve, callnumber,
	 *         duedate, number, barcode.
	 */
	public function getHolding($id, array $patron = null) {
            error_log("aleph getholdings()");
		$holding = array();
		list ($bib, $sys_no) = $this->parseId($id);
		$resource = $bib . $sys_no;
		
		// Check if the 'loadAll' GET variable exists. If not, load only the number of items specified by the user.
		$noOfItemsToLoad = ''; // Inital value of $noOfItemsToLoad
		if (!key_exists('loadAll', $_GET)) {
			// Get the number of items that should be loaded initially. This is for load performance if a record has a lot of items.
			$noOfItemsToLoad = ($this->akConfig->MaxItemsLoad->maxItemsLoad) ? $this->akConfig->MaxItemsLoad->maxItemsLoad : 10;
			if ($noOfItemsToLoad != null && !empty($noOfItemsToLoad)) {
				if (strcasecmp($noOfItemsToLoad, 'all') == 0) {
					$noOfItemsToLoad = ''; // Set no value. This shows all items.
				}
			}
		}
		
		$params = array('view' => 'full', 'noItems' => $noOfItemsToLoad);
		if (! empty($patron['id'])) {
			$params['patron'] = $patron['id'];
		} else if (isset($this->defaultPatronId)) {
			$params['patron'] = $this->defaultPatronId;
		}
		
		$xml = $this->doRestDLFRequest(array('record', $resource, 'items'), $params);
		$moreItemsToLoad = ($xml->{'items'}->{'partial'}) ? true : false;
		$totalNoOfItems = ($moreItemsToLoad) ? 1 : 0; // We use this as "true" (= 1) or "false" (= 0) as we already know through the "partial" value in XML if we should load more items.
		
		foreach ($xml->{'items'}->{'item'} as $item) {
			$item_status = (string) $item->{'z30-item-status-code'}; // $isc
			$item_process_status = (string) $item->{'z30-item-process-status-code'}; // $ipsc
			$sub_library_code = (string) $item->{'z30-sub-library-code'}; // $slc
			$z30 = $item->z30;
			if ($this->translator) {
				$item_status = $this->translator->tab15Translate($sub_library_code, $item_status, $item_process_status);
			} else {
				$item_status = array('opac' => 'Y', 'request' => 'C', 'desc' => (string) $z30->{'z30-item-status'}, 'sub_lib_desc' => (string) $z30->{'z30-sub-library'});
			}
			if ($item_status['opac'] != 'Y') {
				continue;
			}
			$availability = false;
			$reserve = ($item_status['request'] == 'C') ? 'N' : 'Y';
			$collection = (string) $z30->{'z30-collection'};
			$collection_desc = array('desc' => $collection);
			if ($this->translator) {
				$collection_code = (string) $item->{'z30-collection-code'};
				$collection_desc = $this->translator->tab40Translate($collection_code, $sub_library_code);
			}
			$requested = false;
			$duedate = '';
			$addLink = false;
			$status = (string) $item->{'status'};
			
			if (in_array($status, $this->available_statuses)) {
				$availability = true;
			}
			
			// Check for "not available item statuses" in AKsearch.ini:
			$not_available_item_statuses = preg_split('/[\s*,\s*]*,+[\s*,\s*]*/', $this->akConfig->AlephItemStatus->not_available_item_statuses);
			if (in_array($z30->{'z30-item-status'}, $not_available_item_statuses)) {
				$availability = false;
			}
			
			// Check for "not_available_locations" in AKsearch.ini:
			$not_available_locations = preg_split('/[\s*,\s*]*,+[\s*,\s*]*/', $this->akConfig->AlephItemStatus->not_available_locations);
			if (in_array($sub_library_code, $not_available_locations)) {
				$availability = false;
			}
			
			// Get from reading room collections. For these collections, the text "GetFromReadingRoom" (see language files) will be shown
			$readingRoomCollections = (isset($this->akConfig->AlephCollections->reading_room_collections) && !empty($this->akConfig->AlephCollections->reading_room_collections)) ? $this->akConfig->AlephCollections->reading_room_collections : array();
			$readingRoomCollections = (!empty($readingRoomCollections)) ? $readingRoomCollections->toArray() : $readingRoomCollections;
			$getFromReadingRoom = in_array($collection, $readingRoomCollections);
			
			if ($item_status['request'] == 'Y' && $availability == false) {
				$addLink = true;
			}
					
			$isHoldable = false;
			if (!empty($patron)) {
				$hold_request = $item->xpath('info[@type="HoldRequest"]/@allowed');
				$isHoldable = ($hold_request[0] == 'Y') ? true : false;
				$addLink = ($hold_request[0] == 'Y') ? true : false;
			}		
			
			$matches = array();
			if (preg_match("/([0-9]*\\/[a-zA-Z]*\\/[0-9]*);([a-zA-Z ]*)/", $status, $matches)) {
				$duedate = $this->parseDate($matches[1]);
				$requested = (trim($matches[2]) == "Requested");
				$holdtype = ($isHoldable == true) ? 'reserve' : '';
			} else if (preg_match("/([0-9]*\\/[a-zA-Z]*\\/[0-9]*)/", $status, $matches)) {
				$duedate = $this->parseDate($matches[1]);
				$holdtype = ($isHoldable == true) ? 'reserve' : '';
			} else if (preg_match("/([0-9]*\\/[0-9]*\\/[0-9]*)\\s*([0-9]*:[0-9]*)/", $status, $matches)) { // added by AK Bibliothek Wien - MATCH GERMAN ALEPH DATES (E. G. "09/01/15 23:59") TO GET DUE-DATES!
				$duedate = $this->parseDate($matches[1]);
				$holdtype = ($isHoldable == true) ? 'reserve' : '';
			} else {
				$duedate = null;
				$holdtype = ($isHoldable == true) ? 'hold' : '';
			}
						
			// process duedate
			if ($availability) {
				if ($this->duedates) {
					foreach ($this->duedates as $key => $value) {
						if (preg_match($value, $item_status['desc'])) {
							$duedate = $key;
							break;
						}
					}
				} else {
					$duedate = $item_status['desc'];
				}
			} else {
				if ($status == "On Hold" || $status == "Requested") {
					$duedate = "requested";
					$holdtype = ($isHoldable == true) ? 'reserve' : '';
				}
			}
			
			$item_id = $item->attributes()->href;
			$item_id = substr($item_id, strrpos($item_id, '/') + 1);
			$note = (string) $z30->{'z30-note-opac'};
			$holding[] = array(
					'id' => $id,
					'item_id' => $item_id,
					'availability' => $availability,
					'status' => (string) $item_status['desc'],
					'location' => $sub_library_code,
					'reserve' => 'N',
					'callnumber' => (string) $z30->{'z30-call-no'},
					'duedate' => (string) $duedate,
					'number' => (string) $z30->{'z30-inventory-number'},
					'barcode' => (string) $z30->{'z30-barcode'},
					'description' => (string) $z30->{'z30-description'},
					'notes' => ($note == null) ? null : array($note),
					'is_holdable' => $isHoldable,
					'addLink' => $addLink,
					'holdtype' => $holdtype,
					
					// Below are optional attributes
					'collection' => (string) $collection,
					'collection_desc' => (string) $collection_desc['desc'],
					'get_from_readingroom' => $getFromReadingRoom,
					'callnumber_second' => (string) $z30->{'z30-call-no-2'},
					'sub_lib_desc' => (string) $item_status['sub_lib_desc'],
					'no_of_loans' => (string) $z30->{'z30-no-loans'},
					'requested' => (string) $requested,
					'totalNoOfItems' => $totalNoOfItems, // This is necessary for paging (load more items)!!!
			);
		}

		return $holding;
	}
	
	
	/**
	 * Check if the "show more items" link/button should be displayed or not.
	 * 
	 * @param int $noOfTotalItems		Number of total items
	 * @return boolean					true if the link/button should be displayed, false otherwise
	 */
	public function showLoadMore($noOfTotalItems) {
		// If "partial" XML value was found in getHolding, we receive "1" here, if not, we receive "0"
		$showLoadMore = false;
		$showLoadMore = ($noOfTotalItems == 1) ? true : false;
		return $showLoadMore;
	}
	
	public function getNoOfTotalItems() {
		$holding = array();
		list ($bib, $sys_no) = $this->parseId($id);
		$resource = $bib . $sys_no;
		
		$xml = $this->doRestDLFRequest(array('record', $resource, 'items'), $params);
		$moreItemsToLoad = ($xml->{'items'}->{'partial'}) ? true : false;
		$totalNoOfItems = ($moreItemsToLoad) ? $this->getNoOfTotalItems($id) : null;
		
		foreach ($xml->{'items'}->{'item'} as $item) {
			
		}
	}
	
	
	/**
	 * Parse a date.
	 *
	 * Original by: UB/FU Berlin (see VuFind\ILS\Driver\Aleph)
	 * Modified by AK Bibliothek Wien (Michael Birkner): Changed date format for german Aleph
	 *
	 * @param string $date Date to parse
	 *        	
	 * @return string
	 */
	public function parseDate($date) {
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
		} else {
			throw new \Exception("Invalid date: $date");
		}
	}
	
	
	/**
	 * Parse a time.
	 *
	 * @param string $time Time to parse
	 *
	 * @return string
	 */
	public function parseTime($time) {
		if ($time == null || $time == '') {
			return '';
		} else if (preg_match('/^[0-9]{4}$/', $time) === 1) { // 2359
			return $this->dateConverter->convertToDisplayTime('Hi', $time);
		} else {
			throw new \Exception('Invalid time: '.$time);
		}
	}
	
	
	/**
	 * Patron Login
	 *
	 * This is responsible for authenticating a patron against the catalog.
	 * Original by: UB/FU Berlin (see VuFind\ILS\Driver\Aleph)
	 * Modified by AK Bibliothek Wien (Michael Birkner): Login was possible even thoug user was not registered in ILS
	 * 
	 * @param string $user     The patron username
	 * @param string $password The patron's password
	 *
	 * @throws ILSException
	 * @return mixed          Associative array of patron info on successful login, null on unsuccessful login.
	 */
	public function patronLogin($user, $password) {
						
		if ($password == null) {
			$temp = ["id" => $user];
			$temp['college'] = $this->useradm;
			return $this->getMyProfile($temp);
		}
		
		try {
			$xml = $this->doXRequest('bor-auth', ['library' => $this->useradm, 'bor_id' => $user, 'verification' => $password], false);
		} catch (\Exception $ex) {
			throw new ILSException($ex->getMessage());
		}
		
		// Aleph interface error (e. g. verification error)
		$borauthError = ($xml->error != null && !empty($xml->error)) ? (string)$xml->error : null;
		if (isset($borauthError)) {
			if ($borauthError == 'Error in Verification') {
				return null; // Show message for wrong user credentials
			}
			throw new AuthException($borauthError);
		}
		
		$patron = [];
		$name = $xml->z303->{'z303-name'};
		if (strstr($name, ",")) {
			list($lastName, $firstName) = explode(",", $name);
		} else {
			$lastName = $name;
			$firstName = "";
		}
		$email_addr = $xml->z304->{'z304-email-address'};
		$id = $xml->z303->{'z303-id'};
		$home_lib = $xml->z303->z303_home_library;
		// Default the college to the useradm library and overwrite it if the home_lib exists
		$patron['college'] = $this->useradm;
		if (($home_lib != '') && (array_key_exists("$home_lib", $this->sublibadm))) {
			if ($this->sublibadm["$home_lib"] != '') {
				$patron['college'] = $this->sublibadm["$home_lib"];
			}
		}
		$patron['id'] = (string) $id;
		$patron['barcode'] = (string) $user;
		$patron['firstname'] = (string) $firstName;
		$patron['lastname'] = (string) $lastName;
		$patron['cat_username'] = (string) $user;
		$patron['cat_password'] = $password;
		$patron['email'] = (string) $email_addr;
		$patron['major'] = null;
		
		return $patron;
	}
	

	/**
	 * Overriding "getMyProfile" method using X-server from original VuFind Aleph driver.
	 * For our goals (changing user data) we need to gather more information (e. g. ALL
	 * address lines and second phone number).
	 *
	 * @param array $user The patron array
	 *
	 * @throws ILSException
	 * @return array      Array of the patron's profile data on success.
	 */
	public function getMyProfileX($user) {

		$recordList = [];
		if (!isset($user['college'])) {
			$user['college'] = $this->useradm;
		}
		$xml = $this->doXRequest('bor-info', ['loans' => 'N', 'cash' => 'N', 'hold' => 'N', 'library' => $user['college'], 'bor_id' => $user['id']], false);
		
		$id = (string) $xml->z303->{'z303-id'};
		// z304-address-0 is the patrons name field!
		$addressType = (string) $xml->z304->{'z304-address-type'};
		$address1 = (string) $xml->z304->{'z304-address-1'};
		$address2 = (string) $xml->z304->{'z304-address-2'};
		$address3 = (string) $xml->z304->{'z304-address-3'};
		$address4 = (string) $xml->z304->{'z304-address-4'};
		$zip = (string) $xml->z304->{'z304-zip'};
		$email = (string) $xml->z304->{'z304-email-address'};
		$email = trim($email);
		$phone = (string) $xml->z304->{'z304-telephone'};
		$phone2 = (string) $xml->z304->{'z304-telephone-2'};
		$barcode = (string) $xml->z304->{'z304-address-0'};
		$group = (string) $xml->z305->{'z305-bor-status'};
		$expiry = (string) $xml->z305->{'z305-expiry-date'};
		$credit_sum = (string) $xml->z305->{'z305-sum'};
		$credit_sign = (string) $xml->z305->{'z305-credit-debit'};
		$name = (string) $xml->z303->{'z303-name'};
		if (strstr($name, ",")) {
			list($lastname, $firstname) = explode(",", $name);
		} else {
			$lastname = $name;
			$firstname = '';
		}
		if ($credit_sign == null) {
			$credit_sign = 'C';
		}
		$recordList['firstname'] = $firstname;
		$recordList['lastname'] = $lastname;
		if (isset($email)) {
			$recordList['email'] = $email;
		} else if (isset($user['email'])) {
			$recordList['email'] = trim($user['email']);
		} else {
			$recordList['email'] = null;
		}
		$recordList['address_type'] = $addressType;
		$recordList['address1'] = $address1;
		$recordList['address2'] = $address2;
		$recordList['address3'] = $address3;
		$recordList['address4'] = $address4;
		$recordList['zip'] = $zip;
		$recordList['phone'] = $phone;
		$recordList['phone2'] = $phone2;
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
	 * Get historical loans from Aleph API
	 * 
	 * @param array $user From getMyProfile()
	 * 
	 * @return array
	 */
	public function getLoanHistory($user) {	
		$result = $this->getMyTransactions($user, true);
		return $result;
	}
	
	
	/**
	 * Get Patron Transactions
	 *
	 * This is responsible for retrieving all transactions (i.e. checked out items)
	 * by a specific patron.
	 * 
	 * Overwriting original for getting more information on loans and loan history.
	 *
	 * @param array $user    The patron array from patronLogin
	 * @param bool  $history Include history of transactions (true) or just get
	 * current ones (false).
	 *
	 * @throws \VuFind\Exception\Date
	 * @throws ILSException
	 * @return array        Array of the patron's transactions on success.
	 */
	public function getMyTransactions($user, $history = false) {
		$userId = $user['id'];
		$transList = [];
		$params = ["view" => "full"];
		if ($history) {
			$params["type"] = "history";
		}
		$xml = $this->doRestDLFRequest(['patron', $userId, 'circulationActions', 'loans'], $params);
		foreach ($xml->xpath('//loan') as $item) {
			$z36 = $item->z36;
			$z13 = $item->z13;
			$z30 = $item->z30;
			//$group = $item->xpath('@href');
			//$group = substr(strrchr($group[0], "/"), 1);
			$id = (string)$z13->{'z13-doc-number'};
			$renewable = ((string)$item['renew'] == 'Y') ? true : false;
			
			//$renew = $item->xpath('@renew');
			//$docno = (string) $z36->{'z36-doc-number'};
			//$itemseq = (string) $z36->{'z36-item-sequence'};
			//$seq = (string) $z36->{'z36-sequence'};
			$location = (string) $z36->{'z36_pickup_location'};
			$reqnum = (string) $z36->{'z36-doc-number'}.(string) $z36->{'z36-item-sequence'}.(string) $z36->{'z36-sequence'};
			$dueDate = $returned = null;
			$dueTime = null;
			if ($history) {
				$dueDate = (isset($item->z36h->{'z36h-due-date'})) ? (string) $item->z36h->{'z36h-due-date'} : null;
				$dueTime = (isset($item->z36h->{'z36h-due-hour'})) ? (string) $item->z36h->{'z36h-due-hour'} : null;
				$institution = (isset($item->z36h->{'translate-change-active-library'})) ? (string) $item->z36h->{'translate-change-active-library'} : '';
				$loanId = $institution.(string) $item->z36h->{'z36h-number'};
				$returned = (string) $item->z36h->{'z36h-returned-date'};
			} else {
				$dueDate = (isset($z36->{'z36-due-date'})) ? (string) $z36->{'z36-due-date'} : null;
				$dueTime = (isset($z36->{'z36-due-hour'})) ? (string) $z36->{'z36-due-hour'} : null;
				$institution = (isset($z36->{'translate-change-active-library'})) ? (string) $z36->{'translate-change-active-library'} : '';
				$loanId = $institution.(string)$z36->{'z36-number'};
			}
			//$loaned = (string) $z36->{'z36-loan-date'};
			$title = (string) $z13->{'z13-title'};
			$author = (string) $z13->{'z13-author'};
			$isbn = (string) $z13->{'z13-isbn-issn'};
			$barcode = (string) $z30->{'z30-barcode'};
			
			// Calculate due status
			$dueStatus = null;
			if (!$history) {
				$nowTS = mktime(); // Timestamp respecting the timezone. Use time() for timezone UTC
				$dueDateTS = strtotime($dueDate.' '.$dueTime);
				if ($nowTS > $dueDateTS) { // Loan is overdue
					$dueStatus = 'overdue';
				} else if (($dueDateTS - $nowTS) < 86400) { // Due date within one day
					$dueStatus = 'due';
				}
			}
			
			$publicationYear = (isset($z13->{'z13-year'})) ? (string) $z13->{'z13-year'} : null;
			$message = (isset($z30->{'z30-note-opac'})) ? (string) $z30->{'z30-note-opac'} : null;
			
			$transList[] = [
					'duedate' => (($dueDate != null) ? $this->parseDate($dueDate) : null),
					'dueTime' => (($dueTime!= null) ? $this->parseTime($dueTime) : null),
					'dueStatus' => $dueStatus,
					'id' => $id,
					//'source' => '',
					'barcode' => $barcode,
					//'renew' => '',
					//'renewLimit' => '',
					//'request' => '',
					//'volume' => '',
					'publication_year' => $publicationYear,
					'renewable' => $renewable,
					'message' => $message,
					'title' => $title,
					'item_id' => $loanId, // We have to set the loan ID instead the item ID because Aleph renews with the loan ID.
					//'institution_name' => '',
					'isbn' => [$isbn],
					//'issn' => ,
					//'oclc' => '',
					//'upc' => '',
					//'borrowingLocation' => '',
					
					// Other values that are not defined in the VuFind default return array for getMyTransactions()
					'author' => $author,
					'location' => $location,
					'reqnum' => $reqnum,
					'returned' => $this->parseDate($returned),
					//'type' => $type,
			];
		}
		
		return $transList;
	}
	

	
	/**
	 * Overriding "getMyProfile" method using RESTful interface from original VuFind Aleph driver.
	 * For our goals (changing user data) we need to gather more information (e. g. ALL
	 * address lines and second phone number).
	 * @param array $user The patron array
	 *
	 * @throws ILSException
	 * @return array      Array of the patron's profile data on success.
	 */
	public function getMyProfileDLF($user) {
				
		$xml = $this->doRestDLFRequest(['patron', $user['id'], 'patronInformation', 'address']);
		
		$address = $xml->xpath('//address-information');
		$address = $address[0];
		// z304-address-0 does not exist in RESTful response!
		// z304-address-1 is the name field!
		$address1 = (string)$address->{'z304-address-2'};
		$address2 = (string)$address->{'z304-address-3'};
		$address3 = (string)$address->{'z304-address-4'};
		$address4 = (string)$address->{'z304-address-5'};
		$zip = (string)$address->{'z304-zip'};
		$phone = (string)$address->{'z304-telephone-1'};
		$phone2 = (string)$address->{'z304-telephone-2'};
		$email = (string)$address->{'z404-email-address'};
		$dateFrom = (string)$address->{'z304-date-from'};
		$dateTo = (string)$address->{'z304-date-to'};
		if (strpos($address2, ",") === false) {
			$recordList['lastname'] = $address2;
			$recordList['firstname'] = "";
		} else {
			list($recordList['lastname'], $recordList['firstname']) = explode(",", $address2);
		}
		$recordList['address1'] = $address1;
		$recordList['address2'] = $address2;
		$recordList['address3'] = $address3;
		$recordList['address4'] = $address4;
		$recordList['barcode'] = $address1;
		$recordList['zip'] = $zip;
		$recordList['phone'] = $phone;
		$recordList['phone2'] = $phone2;
		$recordList['email'] = $email;
		$recordList['dateFrom'] = $dateFrom;
		$recordList['dateTo'] = $dateTo;
		$recordList['id'] = $user['id'];
		$xml = $this->doRestDLFRequest(['patron', $user['id'], 'patronStatus', 'registration']);
		$status = $xml->xpath("//institution/z305-bor-status");
		$expiry = $xml->xpath("//institution/z305-expiry-date");
		$recordList['expire'] = $this->parseDate($expiry[0]);
		$recordList['group'] = $status[0];
		return $recordList;
	}
	
	
	
	/* ################################################################################################## */
	/* ######################################### AkSearch Begin ######################################### */
	/* ################################################################################################## */
	
	
	/**
	 * Get Aleph journal holding record
	 * 
	 * @param string $id
	 * 			SYS no. of Aleph record
	 * 
	 * @return array
	 */
	public function getJournalHoldings($id) {
            $arrReturnValue = null;

            // Get XML from API
            $bibId = $this->bib[0] . $id;
            $params = array('view' => 'full');
            $xml = $this->doRestDLFRequest(array('record', $bibId, 'holdings'), $params);

            // Iterate over holding entries
            $counter = 0;
            foreach ($xml->{'holdings'}->{'holding'} as $holding) {

                // Get 200 fields of holding
                $xml200Fields = $holding->xpath('datafield[@tag="200"]');

                // If at least one 200-field exists, go on and get the appropriate values.
                // Info: A correct holding only contains one 200 field!
                if (! empty($xml200Fields)) {

                    foreach ($xml200Fields as $key200Field => $xml200Field) {
                        $counter++;

                        // Get values from XML as arrays and glue together the array items to a string (separated by comma)
                        $sublibrary = implode(', ', $xml200Field->xpath('subfield[@code="2"]/text()'));
                        $holdingSummary = implode(', ', $xml200Field->xpath('subfield[@code="b"]/text()'));
                        $gaps = implode(', ', $xml200Field->xpath('subfield[@code="c"]/text()'));
                        $shelfMark = implode(', ', $xml200Field->xpath('subfield[@code="f"]/text()'));
                        $location = implode(', ', $xml200Field->xpath('subfield[@code="g"]/text()'));
                        $locationShelfMark = implode(', ', $xml200Field->xpath('subfield[@code="h"]/text()'));
                        $comment = implode(', ', $xml200Field->xpath('subfield[@code="e"]/text()'));

                        // Add values to array
                        $arrReturnValue[$counter]['sublib'] = $sublibrary;
                        $arrReturnValue[$counter]['holding'] = $holdingSummary;
                        $arrReturnValue[$counter]['gaps'] = $gaps;
                        $arrReturnValue[$counter]['shelfmark'] = $shelfMark;
                        $arrReturnValue[$counter]['location'] = $location;
                        $arrReturnValue[$counter]['locationshelfmark'] = $locationShelfMark;
                        $arrReturnValue[$counter]['comment'] = $comment;

                    }
                }
            }

            return $arrReturnValue;
	}
	
	
	/**
	 * Get the contents of an XML element
	 * 
	 * @param array $inArray
	 * 			Array from SimpleXMLElement
	 * 
	 * @return array
	 */
	public function getXmlFieldContent($inArray) {
		$outArray = '';
		foreach ($inArray as $string) {
			$outArray[] = (string) $string;
		}
		return $outArray;
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
		$subLibName = null;
		if (isset($this->table_sub_library)) {
			$subLibInfoArray = $this->translator->tabSubLibraryTranslate($subLibCode);
			$subLibName = $subLibInfoArray['desc'];
		}
		return $subLibName;
	}
	
	
	/**
	 * {@inheritDoc}
	 * 
	 * @see \VuFind\ILS\Driver\Aleph::getNewItems()
	 */
	public function getNewItems($page, $limit, $daysOld, $fundId = null)
	{
		
		// IMPORTANT: Only items that are already indexed in Solr-Index will can be shown in Frontend!
		// Start request of new items from Aleph X-Services interface only if the user didn't request it with the same parameters in the same session:
		if (!isset($_SESSION['aksNewItems']) || !isset($_SESSION['aksNewItemsDaysOld']) || $_SESSION['aksNewItemsDaysOld'] != $daysOld) {
			$_SESSION['aksNewItems'] = $this->getNewItemsArray($page, $limit, $daysOld, $fundId = null);
			$_SESSION['aksNewItemsDaysOld'] = $daysOld;
		}
		
		return $_SESSION['aksNewItems'];
	}
	
	
	/**
	 * Getting new items from Aleph X-Services interface
	 * 
	 * @param int $page				Page number of results to retrieve (counting starts at 1)
	 * @param int $limit			The size of each page of results to retrieve
	 * @param int $daysOld			The maximum age of records to retrieve in days (max. 30)
	 * @param int $fundId			Optional fund ID to use for limiting results (use a value returned by getFunds, or exclude for no limit); note that "fund" may be a misnomer - if funds are not an appropriate way to limit your new item results, you can return a different set of values from getFunds. The important thing is that this parameter supports an ID returned by getFunds, whatever that may mean.
	 * @return array				Associative array with 'count' and 'results' keys
	 */
	public function getNewItemsArray($page, $limit, $daysOld, $fundId = null) {
    	$newItems = null;
    	
		// Check if date picker was used
		$isDatePicker = false;
		if (substr($daysOld, 0, 11 ) === 'datePicker_') {
			$datePickerFirstDate = substr($daysOld, 11);			
			if ($datePickerFirstDate != null && strlen($datePickerFirstDate) === 8) {
				$isDatePicker = true;
				$dtFirstDate = DateTime::createFromFormat('Ymd', $datePickerFirstDate);//->format('Ymd');
				$datePickerLastDate = $dtFirstDate->format('Ymt');
			}
		}
		
		if ($isDatePicker) {
			$fromInventoryDate = $datePickerFirstDate;
			$toInventoryDate = $datePickerLastDate;
		} else {
			$fromInventoryDate = date('Ymd', strtotime('-'.$daysOld.' days')); // "Today" minus "$daysOld"
			$toInventoryDate = date('Ymd', strtotime('now')); // "Today"
		}
		
		
		// Execute search:
    	$requestText = 'WND='.$fromInventoryDate.'->'.$toInventoryDate.' NOT WEF=(j OR p OR z) NOT WNN=?RA';
    	
		$xFindParams = ['request' => $requestText, 'base' => 'AKW01'];
		$findResult = $this->doXRequest('find', $xFindParams, false);
		$setNumber = $findResult->set_number;
		$noEntries = (int)$findResult->no_entries;
		
		if ($noEntries > 0) {
			
			// Set the "count" value for the return array
			$newItems = ['count' => $noEntries, 'results' => []];
			$from = 1; // Initial "from" value for the "present" request on Aleph X-Services
			$until = 100; // Initial "until" value for the "present" request on Aleph X-Services
			
			if ($noEntries < $until) {
				// Get results and add them to the return array
				$xPresentParams = ['set_entry' => $from.'-'.$until, 'set_number' => $setNumber];
				$presentResult = $this->doXRequest('present', $xPresentParams, false);
				$getSysNos = $presentResult->xpath('//doc_number');
				
				if (!empty($getSysNos)) {
					foreach ($getSysNos as $sysNo) {
						$newItems['results'][] = ['id' => (string)$sysNo];
					}
				}
			} else {
				while ($until <= $noEntries) {
				
					// Get results and add them to the return array
					$xPresentParams = ['set_entry' => $from.'-'.$until, 'set_number' => $setNumber];
					$presentResult = $this->doXRequest('present', $xPresentParams, false);
					$getSysNos = $presentResult->xpath('//doc_number');
				
					if (!empty($getSysNos)) {
						foreach ($getSysNos as $sysNo) {
							$newItems['results'][] = ['id' => (string)$sysNo];
						}
					}
				
					// If "until" is as high as the no. of entries found, we are in the last loop and can break now.
					if ($until == $noEntries) {
						break;
					}
				
					// Set values to get the next 100 results
					$from = $from + 100;
					$until = $until + 100;
				
					// "until" may not be higher than the no. of entries found, otherwise we get an error from Aleph
					if ($until > $noEntries) {
						$until = $noEntries;
					}
				}
			}
		}
		
		return $newItems;
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
    	    	
    	$statusMessage = 'Changing password not successful!';
    	$success = false;
    	
    	$patron = $details['patron'];
    	$barcode = trim(htmlspecialchars($patron['barcode'], ENT_COMPAT, 'UTF-8'));
    	$catPassword = trim(htmlspecialchars($patron['cat_password'], ENT_COMPAT, 'UTF-8'));
		
    	$oldPassword = trim(htmlspecialchars($details['oldPassword'], ENT_COMPAT, 'UTF-8'));
    	$newPassword = trim(htmlspecialchars($details['newPassword'], ENT_COMPAT, 'UTF-8'));
    	
    	if ($catPassword == $oldPassword) {
    		if (strlen($newPassword) >= 4) {
    			
    			// Set XML string for updating patron:
    			$xml_string = '<?xml version="1.0"?>
								<p-file-20>
									<patron-record>
										<z303>
											<match-id-type>01</match-id-type>
											<match-id>' . $barcode . '</match-id>
											<record-action>U</record-action>
											<z303-user-library>AKW50</z303-user-library>
											<z303-home-library>XAW1</z303-home-library>
										</z303>
										<z308>
											<record-action>U</record-action>
											<z308-key-type>01</z308-key-type>
											<z308-key-data>' . $barcode . '</z308-key-data>
											<z308-verification>' . $newPassword . '</z308-verification>
											<z308-verification-type>00</z308-verification-type>
											<z308-status>AC</z308-status>
											<z308-encryption>N</z308-encryption>
										</z308>
									</patron-record>
								</p-file-20>
								';
    			
    			// Remove whitespaces from XML string:
    			$xml_string = preg_replace("/\n/i", "", $xml_string);
    			$xml_string = preg_replace("/>\s*</i", "><", $xml_string);

    			$xParams = ['library' => 'AKW50', 'update-flag' => 'Y', 'xml_full_req' => $xml_string];
    			$xResult = $this->doXRequest('update-bor', $xParams, false);
    			
    			// Error handling from X-Server-Request (e. g. Error 403 "Forbidden")
    			$xmlErrorTitle = ($xResult->head->title != null && !empty($xResult->head->title)) ? (string)$xResult->head->title : null;
    			if (isset($xmlErrorTitle)) {
					throw new AuthException($xmlErrorTitle. ': '. $xResult->body->h1);
				}
				
				
				// We got this far so the password change should be a success
    			$statusMessage = 'Changed password';
    			$success = true;
    			    			
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
     * Changing the data of a user
     * 
     * @param	array	$details An array of patron details that should be updated in Aleph
     * 
	 * @return	array	Result array containing 'success' (true or false) and 'status' (status message)
     */
    
    public function changeUserData($details) {
    	// 0. Click button in changeuserdata.phtml
		// 1. AkSitesController.php->changeUserDataAction()
		// 2. Manager.php->updateUserData()
		// 3. ILS.php->updateUserData()
		// 4. Aleph.php->changeUserData();
		
    	// Initialize variables:
    	$success = false;
    	$statusMessage = 'Could not change user data.';
    	$dateToday = date("Ymd");
    	$barcode = $details['username'];
    	$addressType = $details['address_type'];
    	
    	$email = (isset($details['email'])) ? trim($details['email']) : '';
    	$phone = (isset($details['phone'])) ? trim($details['phone']) : '';
    	$phone2 = (isset($details['phone2'])) ? trim($details['phone2']) : '';

    	if (empty($email)) {
    		throw new AuthException('required_fields_empty');
    	}
    	
    	// XML string for changing data in Aleph via X-Services
    	$xml_string = '<?xml version="1.0"?>
		<p-file-20>
			<patron-record>
				<z303>
					<match-id-type>01</match-id-type>
					<match-id>' . $barcode . '</match-id>
					<record-action>U</record-action>
					<z303-user-library>AKW50</z303-user-library>
					<z303-update-date>' . $dateToday . '</z303-update-date>
					<z303-home-library>XAW1</z303-home-library>
				</z303>
				<z304>
					<record-action>U</record-action>
					<z304-address-type>' . $addressType . '</z304-address-type>
					<email-address>' . $email . '</email-address>
					<z304-email-address>' . $email . '</z304-email-address>
					<z304-telephone>' . $phone . '</z304-telephone>
					<z304-telephone-2>' . $phone2 . '</z304-telephone-2>
					<z304-update-date>' . $dateToday . '</z304-update-date>
				</z304>
			</patron-record>
		</p-file-20>
		';
    	
		// Remove whitespaces from XML string:
		$xml_string = preg_replace("/\n/i", "", $xml_string);
		$xml_string = preg_replace("/>\s*</i", "><", $xml_string);

		$xParams = ['library' => 'AKW50', 'update-flag' => 'Y', 'xml_full_req' => $xml_string];
		$xResult = $this->doXRequest('update-bor', $xParams, false);
		
		// Error handling from X-Server-Request (e. g. Error 403 "Forbidden")
		$xmlErrorTitle = ($xResult->head->title != null && !empty($xResult->head->title)) ? (string)$xResult->head->title : null;
		if (isset($xmlErrorTitle)) {
			throw new AuthException($xmlErrorTitle. ': '. $xResult->body->h1);
		}
		
		// We got this far so the user data update should be a success
		$statusMessage = 'Successfully changed user data';
		$success = true;
    	
		// Create return array
    	$returnArray = array('success' => $success, 'status' => $statusMessage);
    	
    	return $returnArray;
    }
    
    
    /**
     * Check if ILS holding records (items) exists for a certain record ID. This is a convenient method for enabling or disabling
     * things like the "Holding" tab without the need to process the holding data from the API.
     * 
     * @param string	$id
     * @return boolean	true if at least one holding (item) exists, false otherwise.
     */
    public function hasIlsHoldings($id) {
    	$hasIlsHoldings = false;
    	list ($bib, $sys_no) = $this->parseId($id);
    	$resource = $bib . $sys_no;
    	$xml = $this->doRestDLFRequest(array('record', $resource, 'items'), null);
    	if (count($xml->items) > 0) {
    		$hasIlsHoldings = true;
    	}
    	return $hasIlsHoldings;
    }
    
    
    /**
     * Check if journal holding records exists for a certain record ID. This is a convenient method for enabling or disabling
     * things like the "JournalHolding" tab without the need to process the holding data from the API.
     *
     * @param string	$id
     * @return boolean	true if at least one journal holding exists, false otherwise.
     */
    public function hasJournalHoldings($id) {
    	$hasJournalHoldings = false;
    	
    	//$bibId = $this->bib[0] . $id;
    	//$xml = $this->doRestDLFRequest(array('record', $bibId, 'holdings'));
    	list ($bib, $sys_no) = $this->parseId($id);
    	$resource = $bib . $sys_no;
    	$xml = $this->doRestDLFRequest(array('record', $resource, 'holdings'), null);
    	
    	if (count($xml->holdings->holding) > 0) {
    		$hasJournalHoldings = true;
    	}
    	
    	return $hasJournalHoldings;
    }
    
    
    /**
     * Check if journal holding OR item holding exists
     * 
     * @param string $id
     * @return boolean
     */
    public function hasIlsOrJournalHoldings($id) {    	
    	$hasIlsOrJournalHoldings = false;
    	if ($this->hasIlsHoldings($id)) {
    		$hasIlsOrJournalHoldings = true;
    	} else {	
    		if ($this->hasJournalHoldings($id)) {
    			$hasIlsOrJournalHoldings = true;
    		}
    	}
    	return $hasIlsOrJournalHoldings;
    }
    
    
    
	/* ################################################################################################## */
	/* ########################################## AkSearch End ########################################## */
	/* ################################################################################################## */
}




class AlephTranslator extends AlephTranslatorDefault {

	/**
	 * Parse a table
	 * 
	 * Modified by AK Bibliothek Wien (Michael Birkner): Repaired 2 bugs
	 * 
	 * @param string $file
	 *        	Input file
	 * @param string $callback
	 *        	Callback routine for parsing
	 *        	
	 * @return string
	 */
	public function parsetable($file, $callback) {
		$result = array();
		$file_handle = fopen($file, "r, ccs=UTF-8");
		
		// FIXME: BUG - Found by AK Bibliothek Wien
		// If something is wrong with the file (e. g. no access rights for PHP) and we don't catch it
		// here, "feof" further down could generate an error and result in a very big apache2-log-file
		// (multiple GB) and consumes all RAM (kills the machine).
		// Solution: check if $file_handle == false here!
		if ($file_handle == false) {
			echo '<br /><b>Problem with ' . $file . '! Is it readable by PHP (check access rights of directory and file)?</b><br />';
			return;
		}
		
		$rgxp = "";
		while (! feof($file_handle)) {
			$line = fgets($file_handle);
			$line = chop($line);
			
			if (preg_match("/!!/", $line)) {
				$line = chop($line);
				$rgxp = AlephTranslator::regexp($line);
			}
			if (preg_match("/!.*/", $line) || $rgxp == "" || $line == "") {
				// All comment lines that begin with "!"
			} else {
				// FIXME: BUG - Found by AK Bibliothek Wien:
				// 80 for padding is to small! We need to take 94. If not, preg_match will be false
				// and the return value would be empty!
				// ORIGINAL: $line = str_pad($line, 80);
				$line = str_pad($line, 94);
				
				$matches = "";
				if (preg_match($rgxp, $line, $matches)) {
					call_user_func_array($callback, array($matches, &$result, $this->charset));
				}
			}
		}
		fclose($file_handle);
		return $result;
	}
}