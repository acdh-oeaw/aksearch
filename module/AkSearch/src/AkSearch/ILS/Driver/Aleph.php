<?php
/**
 * Aleph Driver for AkSearch
 *
 * PHP version 5
 *
 * Copyright (C) AK Bibliothek Wien 2015.
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

use VuFind\Exception\ILS as ILSException;
use Zend\Log\LoggerInterface;
use VuFindHttp\HttpServiceInterface;
use DateTime;
use VuFind\Exception\Date as DateException;
use VuFind\SimpleXML;
use VuFind\ILS\Driver\Aleph as AlephDefault;
use VuFind\ILS\Driver\AlephTranslator as AlephTranslatorDefault;
use VuFind\ILS\Driver\AlephRestfulException as AlephRestfulExceptionDefault;


class Aleph extends AlephDefault {

	
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
		// $url = "http://$this->host/X?op=$op";
		$url = "https://$this->host/X?op=$op";
		$url = $this->appendQueryString($url, $params);
		if ($auth) {
			$url = $this->appendQueryString($url, array('user_name' => $this->wwwuser, 'user_password' => $this->wwwpasswd));
		}

		$result = $this->doHTTPRequest($url);
		if ($result->error && $result->error != 'empty set') { // Excluding "empty set" prevents error message for empty "getNewItems" result
			if ($this->debug_enabled) {
				$this->debug("XServer error, URL is $url, error message: $result->error.");
			}
			throw new ILSException("XServer error: $result->error.");
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
		// $url = "http://$this->host:$this->dlfport/rest-dlf/" . $path;
		$url = "https://$this->host/rest-dlf/" . $path;
		$url = $this->appendQueryString($url, $params);
		
		$result = $this->doHTTPRequest($url, $method, $body);
		$replyCode = (string) $result->{'reply-code'};
		if ($replyCode != "0000") {
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
		$holding = array();
		list ($bib, $sys_no) = $this->parseId($id);
		$resource = $bib . $sys_no;
		$params = array('view' => 'full');
		if (! empty($patron['id'])) {
			$params['patron'] = $patron['id'];
		} else if (isset($this->defaultPatronId)) {
			$params['patron'] = $this->defaultPatronId;
		}
		$xml = $this->doRestDLFRequest(array('record', $resource, 'items'), $params);
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
			if ($item_status['request'] == 'Y' && $availability == false) {
				$addLink = true;
			}
			if (! empty($patron)) {
				$hold_request = $item->xpath('info[@type="HoldRequest"]/@allowed');
				$addLink = ($hold_request[0] == 'Y');
			}
			$matches = array();
			if (preg_match("/([0-9]*\\/[a-zA-Z]*\\/[0-9]*);([a-zA-Z ]*)/", $status, $matches)) {
				$duedate = $this->parseDate($matches[1]);
				$requested = (trim($matches[2]) == "Requested");
			} else if (preg_match("/([0-9]*\\/[a-zA-Z]*\\/[0-9]*)/", $status, $matches)) {
				$duedate = $this->parseDate($matches[1]);
			} else if (preg_match("/([0-9]*\\/[0-9]*\\/[0-9]*)\\s*([0-9]*:[0-9]*)/", $status, $matches)) { // added by AK Bibliothek Wien - MATCH GERMAN ALEPH DATES (E. G. "09/01/15 23:59") TO GET DUE-DATES!
				$duedate = $this->parseDate($matches[1]);
			} else {
				$duedate = null;
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
				}
			}
			$item_id = $item->attributes()->href;
			$item_id = substr($item_id, strrpos($item_id, '/') + 1);
			$note = (string) $z30->{'z30-note-opac'};
			$holding[] = array('id' => $id, 'item_id' => $item_id, 'availability' => $availability, 'status' => (string) $item_status['desc'], 'location' => $sub_library_code, 'reserve' => 'N', 'callnumber' => (string) $z30->{'z30-call-no'}, 'duedate' => (string) $duedate, 'number' => (string) $z30->{'z30-inventory-number'}, 'barcode' => (string) $z30->{'z30-barcode'}, 'description' => (string) $z30->{'z30-description'}, 'notes' => ($note == null) ? null : array($note), 'is_holdable' => true, 'addLink' => $addLink, 'holdtype' => 'hold',
                /* below are optional attributes*/
                'collection' => (string) $collection, 'collection_desc' => (string) $collection_desc['desc'], 'callnumber_second' => (string) $z30->{'z30-call-no-2'}, 'sub_lib_desc' => (string) $item_status['sub_lib_desc'], 'no_of_loans' => (string) $z30->{'$no_of_loans'}, 'requested' => (string) $requested);
		}
		return $holding;
	}

	/**
	 * Parse a date.
	 *
	 * Original by: UB/FU Berlin (see VuFind\ILS\Driver\Aleph)
	 * Modified by AK Bibliothek Wien (Michael Birkner): Changed date format for german Aleph
	 *
	 * @param string $date
	 *        	Date to parse
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
		
		$xmlGetHol = null;
		$bibId = $this->bib[0] . $id;
		$xmlGetHolList = $this->doRestDLFRequest(array('record', $bibId, 'holdings'));
		                                                                               
		// Get links to all holding-records:
		$xmlGetHolListHrefs = $xmlGetHolList->xpath('//holding[@href]');

		// Add links to holding-records to an array:
		foreach ($xmlGetHolListHrefs as $xmlGetHolListHref) {
			$arrHolListHrefs[] = $xmlGetHolListHref['href'];
		}
		
		foreach ($arrHolListHrefs as $holdingHref) {

			$xmlGetHol = simplexml_load_file($holdingHref);
			$xml200Fields = $xmlGetHol->xpath('//datafield[@tag="200"]');
			
			if (! empty($xml200Fields)) { // If at least one 200-field exists, go on and get the appropriate values
				
				foreach ($xml200Fields as $key200Field => $xml200Field) {
					
					$xmlHolingsTest = $xml200Field->xpath('//subfield[@code="b"]/text()');
					$arrHoldingsTest = $this->getXmlFieldContent($xmlHolingsTest);
					
					if (! empty($arrHoldingsTest)) {
						foreach ($arrHoldingsTest as $key => $string) {
							$arrReturnValueTest[$key200Field][$key]['holding'] = $string;
						}
					}
				}
				
				// Get values from XML (returns an array with "SimpleXMLElement Object")
				$xmlSublibrary = $xmlGetHol->xpath('//datafield[@tag="200"]/subfield[@code="2"]/text()');
				$xmlHolings = $xmlGetHol->xpath('//datafield[@tag="200"]/subfield[@code="b"]/text()');
				$xmlGaps = $xmlGetHol->xpath('//datafield[@tag="200"]/subfield[@code="c"]/text()');
				$xmlShelfMark = $xmlGetHol->xpath('//datafield[@tag="200"]/subfield[@code="f"]/text()');
				$xmlLocation = $xmlGetHol->xpath('//datafield[@tag="200"]/subfield[@code="g"]/text()');
				$xmlLocationShelfMark = $xmlGetHol->xpath('//datafield[@tag="200"]/subfield[@code="h"]/text()');
				$xmlComment = $xmlGetHol->xpath('//datafield[@tag="200"]/subfield[@code="e"]/text()');
				
				$arrSublibrary = $this->getXmlFieldContent($xmlSublibrary);
				$arrHoldings = $this->getXmlFieldContent($xmlHolings);
				$arrGaps = $this->getXmlFieldContent($xmlGaps);
				$arrShelfMark = $this->getXmlFieldContent($xmlShelfMark);
				$arrLocation = $this->getXmlFieldContent($xmlLocation);
				$arrLocationShelfMark = $this->getXmlFieldContent($xmlLocationShelfMark);
				$arrComment = $this->getXmlFieldContent($xmlComment);
				
				if (! empty($arrSublibrary)) {
					foreach ($arrSublibrary as $key => $string) {
						$arrReturnValue[$key]['sublib'] = $string;
					}
				}
				
				if (! empty($arrHoldings)) {
					foreach ($arrHoldings as $key => $string) {
						$arrReturnValue[$key]['holding'] = $string;
					}
				}
				
				if (! empty($arrGaps)) {
					foreach ($arrGaps as $key => $string) {
						$arrReturnValue[$key]['gaps'] = $string;
					}
				}
				
				if (! empty($arrShelfMark)) {
					foreach ($arrShelfMark as $key => $string) {
						$arrReturnValue[$key]['shelfmark'] = $string;
					}
				}
				
				if (! empty($arrLocation)) {
					foreach ($arrLocation as $key => $string) {
						$arrReturnValue[$key]['location'] = $string;
					}
				}
				
				if (! empty($arrLocationShelfMark)) {
					foreach ($arrLocationShelfMark as $key => $string) {
						$arrReturnValue[$key]['locationshelfmark'] = $string;
					}
				}
				
				if (! empty($arrComment)) {
					foreach ($arrComment as $key => $string) {
						$arrReturnValue[$key]['comment'] = $string;
					}
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
	public function getNewItemsArray($page, $limit, $daysOld, $fundId = null)
    {
    	$newItems = null;
    	
    	$fromInventoryDate = date('Ymd', strtotime('-'.$daysOld.' days')); // "Today" minus "$daysOld"
    	$toInventoryDate = date('Ymd', strtotime('now')); // "Today"
    	
		// Execute search:
		$requestText = 'WND='.$fromInventoryDate.'->'.$toInventoryDate.' NOT WEF=(j OR p OR z) NOT WNN=?RA NOT WNN=?SP';
		
		$xFindParams = ['request' => $requestText, 'base' => 'AKW01'];
		//$xFindParams = ['request' => 'WND='.$fromInventoryDate.'->'.$toInventoryDate.' NOT WEF=(j OR p OR z) NOT WNN=?RA NOT WNN=?SP', 'base' => 'AKW01'];
		$findResult = $this->doXRequest('find', $xFindParams, false);
		$setNumber = $findResult->set_number;
		$noEntries = (int)$findResult->no_entries;
		
		if ($noEntries > 0) {
			
			// Set the "count" value for the return array
			$newItems = ['count' => $noEntries, 'results' => []];
			
			// Get results and add them to the return array
			$xPresentParams = ['set_entry' => '1-3', 'set_number' => $setNumber];
			$presentResult = $this->doXRequest('present', $xPresentParams, false);
			$getSysNos = $presentResult->xpath('//doc_number');
			
			$from = 1; // Initial "from" value for the "present" request on Aleph X-Services
			$until = 100; // Initial "until" value for the "present" request on Aleph X-Services
			
			while ($until <= $noEntries) {
				
				// Get results and add them to the return array
				$xPresentParams = ['set_entry' => $from.'-'.$until, 'set_number' => $setNumber];
				$presentResult = $this->doXRequest('present', $xPresentParams, false);
				$getSysNos = $presentResult->xpath('//doc_number');
				
				if (!empty($getSysNos)) {
					foreach ($getSysNos as $key => $sysNo) {
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
		
		return $newItems;
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
