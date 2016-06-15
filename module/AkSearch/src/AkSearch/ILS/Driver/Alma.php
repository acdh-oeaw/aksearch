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
use VuFind\Exception\Auth as AuthException;
use Zend\Log\LoggerInterface;
use VuFindHttp\HttpServiceInterface;
use DateTime;
use VuFind\Exception\Date as DateException;
use VuFind\SimpleXML;



// Show PHP errors:
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(- 1);


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
	 * {@inheritDoc}
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
			//$duedate							= 'string';
			//$returnDate						= 'string';
			$number								= $key;
			//$requests_placed					= 'string or number';
			$barcode							= (string)$item->item_data->barcode;
			$public_note						= (string)$item->item_data->public_note;
			$notes								= ($public_note == null) ? null : [$public_note]; // Deprecated in VuFind 3.0 in favor of holdings_notes
			$holdings_notes						= ($public_note == null) ? null : [$public_note]; // New in VuFind 3.0
			$item_notes							= ($public_note == null) ? null : [$public_note]; // New in VuFind 3.0
			//$summary							= array();
			//$supplements						= array();
			//$indexes							= array();
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
			
			$returnValue[] = [
					// Array fields described on VuFind ILS page:
					'id'								=> $id,
					'availability'						=> $availability,
					'status'							=> $status,
					'location'							=> $location,
					'reserve'							=> $reserve,
					'callnumber'						=> $callnumber,
					//'duedate'							=> 'string',
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
					'requested'							=> $requested
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
	protected function doHTTPRequest($url, $method = 'GET') {

		if ($this->debug_enabled) {
			$this->debug("URL: '$url'");
		}
			
		$result = null;
		$statusCode = null;
		$returnArray = null;
		
		try {
			$client = $this->httpService->createClient($url);
			$client->setMethod($method);
			$result = $client->send();
			$statusCode = $result->getStatusCode();
		} catch (\Exception $e) {
			throw new ILSException($e->getMessage());
		}
		
		echo 'HERE: '.$statusCode;
		
		if (!$result->isSuccess()) {
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
			throw new ILSException(
					"XML is not valid or HTTP error, URL: $url method: $method answer: $answer, HTTP status code: $statusCode."
					);
		}
		
		$returnArray = ['xml' => $xml, 'status' => $statusCode];
		
		return $returnArray;
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
		
		
		$result = $this->doHTTPRequest($this->apiUrl.'users/'.$user.'?user_id_type=all_unique&op=auth&password='.$password.'&apikey='.$this->apiKey, 'POST');
		
		return null;
	
		/*
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
		*/
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
}
?>