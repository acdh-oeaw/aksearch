<?php
/**
 * Model for MAB records in Solr.
 *
 * PHP version 5
 *
 * Copyright (C) AK Bibliothek Wien 2015.
 * Some code parts ware adopted from VuFind\RecordDriver\SolrMarc. They are marked accordingly.
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
 * @package  RecordDrivers
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://wien.arbeiterkammer.at/service/bibliothek/
 */
 
namespace AkSearch\RecordDriver;
use VuFind\Exception\ILS as ILSException;
use VuFind\RecordDriver\SolrDefault as SolrDefault;
use ProxyManagerTestAsset\EmptyClass;

class SolrMab extends SolrDefault implements \VuFindHttp\HttpServiceAwareInterface {
	
	use \VuFindHttp\HttpServiceAwareTrait;

     /**
     * ILS connection
     *
     * @var \VuFind\ILS\Connection
     */
    protected $ils = null;
    
    /**
     * Hold logic
     *
     * @var \VuFind\ILS\Logic\Holds
     */
    protected $holdLogic;
    
    /**
     * Title hold logic
     *
     * @var \VuFind\ILS\Logic\TitleHolds
     */
    protected $titleHoldLogic;
    
    /**
     * AKsearch.ini configuration
     */
    protected $akConfig;

    
    /**
     * Constructor
     * 
     * Geting values from AKsearch.ini
     */
    public function __construct($mainConfig = null, $recordConfig = null, $searchSettings = null, $akConfig = null) {
    	
    	// Get AKsearch.ini config
    	// See 4th parameter for "new SolrMab(...)" in method "getSolrMab()" of class "AkSearch\RecordDriver\Factory"
    	$this->akConfig = (isset($akConfig)) ? $akConfig : null;
    	
    	// Call parent constructor
    	parent::__construct($mainConfig, $recordConfig, $searchSettings);
    }
    
    
    /**
     * These Solr fields should NEVER be used for snippets.  (We exclude author
     * and title because they are already covered by displayed fields; we exclude
     * spelling because it contains lots of fields jammed together and may cause
     * glitchy output; we exclude ID because random numbers are not helpful).
     * 
     * Addition for AkSearch: we exclude title_de, title_wildcard, author_de, author_wildcard
     * and some others too.
     *
     * @var array
     */
    protected $forbiddenSnippetFields = [
    		'author', 'author-letter', 'title', 'title_short', 'title_full',
    		'title_full_unstemmed', 'title_auth', 'spelling', 'id',
    		'ctrlnum',
    		'title_de', 'title_wildcard', 'author_de', 'author_wildcard',
    		/*'corporateAuthorName_txt', 'corporateAuthor2Name_txt_mv',
    		'corporateAuthor2NameGnd_txt_mv'*/
    ];
    
    
    
    /**
     * Call entity facts API (BETA)
     * 
     * @return JSON
     */
    public function getEntityFact($gndId) {
    	
    	// Example: http://hub.culturegraph.org/entityfacts/118540238
    	$gndId = '118540238';
    	$client = $this->httpService->createClient('http://hub.culturegraph.org/entityfacts/'.$gndId);
    	$client->setMethod('GET');
    	$result = $client->send();
    	
    	if (!$result->isSuccess()) {
    		throw new ILSException('HTTP error');
    	}
    	$answer = $result->getBody();
    	
    	/*
    	echo '<pre>';
    	//print_r($client->getLastRequest ());
    	print_r($answer);
    	echo '</pre>';
    	*/
    }
    
    /**
     * Pick one line from the highlighted text (if any) to use as a snippet.
     *
     * @return mixed False if no snippet found, otherwise associative array
     * with 'snippet' and 'caption' keys.
     */
    public function getHighlightedSnippet()
    {    	
    	// Only process snippets if the setting is enabled:
    	if ($this->snippet) {
    		// First check for preferred fields:
    		foreach ($this->preferredSnippetFields as $current) {
    			if (isset($this->highlightDetails[$current][0])) {
    				return [
    						'snippet' => $this->highlightDetails[$current][0],
    						'caption' => $this->getSnippetCaption($current)
    				];
    			}
    		}
    
    		// No preferred field found, so try for a non-forbidden field:
    		if (isset($this->highlightDetails)
    				&& is_array($this->highlightDetails)
    				) {
    					foreach ($this->highlightDetails as $key => $value) {
    						if (!in_array($key, $this->forbiddenSnippetFields)) {
    							return [
    									'snippet' => $value[0],
    									'caption' => $this->getSnippetCaption($key)
    							];
    						}
    					}
    				}
    	}
    
    	// If we got this far, no snippet was found:
    	return false;
    }
    
    
    /**
     * Return an XML representation of the record.
     */
    public function getXML($format = null, $baseUrl = null, $recordLink = null) {
    	$xmlOrFullRecord = $this->fields['fullrecord'];
    	
    	// Masking call nos. and collections
    	$strMarcFieldsForMasking = $this->akConfig->Masking->marcfields;
    	if (isset($strMarcFieldsForMasking) && !empty($strMarcFieldsForMasking)) {
    		$arrMarcFieldsForMasking = explode(',', $strMarcFieldsForMasking);
    		$simpleXML = simplexml_load_string($xmlOrFullRecord);
    		foreach ($simpleXML->record->datafield as $datafield) {
    			foreach ($arrMarcFieldsForMasking as $marcFieldForMasking) {
    				$marcFieldForMasking = trim($marcFieldForMasking);
    				$tagToMask = substr($marcFieldForMasking, 0, 3);
    				$ind1ToMask = substr($marcFieldForMasking, 4, 1);
    				$ind2ToMask = substr($marcFieldForMasking, 5, 1);
    				$subfToMask = substr($marcFieldForMasking, 7, 1);
    				$mode = trim(substr($marcFieldForMasking, 8, strlen($marcFieldForMasking)));
    				if ($mode == '[all]') {
    					$mode = 'all';
    				} else {
    					$mode = 'begins';
    				}
    				
    				$tag = $datafield->attributes()->tag;
    				$ind1 = $datafield->attributes()->ind1;
    				$ind2 = $datafield->attributes()->ind2;
    				 
    				if ($tag == $tagToMask) { // Check for tag
    					if ($ind1 == $ind1ToMask || $ind1ToMask == '*') { // Check for indicator 1
    						if ($ind2 == $ind2ToMask || $ind2ToMask == '*') { // Check for indicator 2
    							$subfieldCounter = -1;
    							foreach ($datafield->subfield as $subfield) {
    								$subfieldCounter = $subfieldCounter + 1;
    								foreach ($subfield->attributes() as $subfieldCode) {
    									if ($subfieldCode == $subfToMask) {
    										$datafield->subfield->$subfieldCounter = $this->getMaskedValue($subfield, $mode);
    									}
    								}
    							}
    						}
    					}
    				}
    			}
    		}
    	}
    	
    	return $simpleXML->asXML();
    }
    
    /**
     * Getting raw solr field for staff view ... but first apply masking.
     * 
     * {@inheritDoc}
     * @see \VuFind\RecordDriver\AbstractBase::getRawData()
     */
    public function getRawData() {
    	$raw = $this->fields;
    	
    	// Masking solr fields
    	$strMaskingSolrFields = $this->akConfig->Masking->solrfields;
    	if (isset($strMaskingSolrFields) && !empty($strMaskingSolrFields)) {
    		$arrMaskingSolrFields = preg_split('/\s*,\s*/', trim($strMaskingSolrFields));

    		foreach ($raw as $fieldname => &$fieldvalue) {
    			if (is_array($fieldvalue)) {
    				foreach ($fieldvalue as &$value) {
    					foreach ($arrMaskingSolrFields as $solrFieldToMask) {
	    					$mode = 'begins';
	    					if (preg_match('/\[all\]/', $solrFieldToMask)) {
	    						$mode = 'all';
	    						$solrFieldToMask = preg_replace('/\[all\]/', '', $solrFieldToMask);
	    					} else if (preg_match('/\[begins\]/', $solrFieldToMask)) {
	    						$mode = 'begins';
	    						$solrFieldToMask = preg_replace('/\[begins\]/', '', $solrFieldToMask);
	    					}
	    					if ($fieldname == $solrFieldToMask) {
	    						$value = $this->getMaskedValue($value, $mode);
	    					}
	    				}
    				}
    			} else {
    				foreach ($arrMaskingSolrFields as $solrFieldToMask) {
    					$mode = 'begins';
    					if (preg_match('/\[all\]/', $solrFieldToMask)) {
    						$mode = 'all';
    						$solrFieldToMask = preg_replace('/\[all\]/', '', $solrFieldToMask);
    					} else if (preg_match('/\[begins\]/', $solrFieldToMask)) {
    						$mode = 'begins';
    						$solrFieldToMask = preg_replace('/\[begins\]/', '', $solrFieldToMask);
    					}
    					if ($fieldname == $solrFieldToMask) {
    						$fieldvalue = $this->getMaskedValue($fieldvalue, $mode);
    					}
    				}
    			}
    		}
    		
    	}
    	
    	return $raw;
    }
    
    
    /**
     * Get text that can be displayed to represent this record in breadcrumbs.
     *
     * @return string: Breadcrumb text to represent this record.
     */
    public function getBreadcrumb() {
    	return str_replace(array("<", ">"), "", $this->getShortTitle());
    	
    }
    
	/**
	 * Get Solrfield shelfmark_txt_mv (shelfmarks [Signaturen])
	 * 
	 * @return array or null if empty
	 */  
	public function getShelfMarks() {
		return isset($this->fields['shelfmark_txt_mv']) ? $this->fields['shelfmark_txt_mv'] : null;
	}
	
	/**
	 * Get Solrfield leader_str (Marc leader)
	 *
	 * @return string or null if empty
	 */
	public function getLeader() {
		return isset($this->fields['leader_str']) ? $this->fields['leader_str'] : null;
	}
	
	/**
	 * Get Solrfield sysNo_txt (SYS no. of Aleph)
	 *
	 * @return string or null if empty
	 */
	public function getSysNo() {
		return isset($this->fields['sysNo_txt']) ? $this->fields['sysNo_txt'] : null;
	}

	
	/**
	 * Getting the cover or icon (according to format and/or publication type)
	 * 
	 * {@inheritDoc}
	 * @see \VuFind\RecordDriver\SolrDefault::getThumbnail()
	 */
	public function getThumbnail($size = 'small') {
		
		if (isset($this->fields['thumbnail']) && $this->fields['thumbnail']) {
			return $this->fields['thumbnail'];
		}
		
		// Get publication type as string:
		$publicationTypeCode = $this->getPublicationTypeCode();
		
		// Get formats as array:
		$formats = (array_key_exists('format', $this->fields)) ? $this->fields['format'] : null;
		$format = null;
		if (isset($formats)) {
			if (in_array('printed', $formats)) {
				// Default for "printed" format. Overwrite below if other format is available.
				$format = 'book';
				
				if (isset($publicationTypeCode)) {
					if ($publicationTypeCode == 'm' || $publicationTypeCode == 's') {
						$format = 'book';
					} else if ($publicationTypeCode == 'n' || $publicationTypeCode == 't' || $publicationTypeCode == 'r') {
						$format = 'books';
					} else if ($publicationTypeCode == 'a') {
						$format = 'article';
					} else if ($publicationTypeCode == 'j' || $publicationTypeCode == 'p' || $publicationTypeCode == 'f') {
						$format = 'journal';
					} else if ($publicationTypeCode == 'z') {
						$format = 'newspaper';
					}
				}
			} else if (in_array('manuscript', $formats)) {
				$format = 'manuscript';
			} else if (in_array('mixedmedia', $formats)) {
				$format = 'mixedmedia';
			} else if (in_array('microform', $formats)) {
				$format = 'microform';
			} else if (in_array('soundcarrier', $formats)) {
				$format = 'music';
			} else if (in_array('avunknown', $formats)) {
				$format = 'avunknown';
			} else if (in_array('filmforprojection', $formats)) {
				$format = 'filmforprojection';
			} else if (in_array('videorecording', $formats)) {
				$format = 'video';
			} else if (in_array('dvd', $formats)) {
				$format = 'dvd';
			} else if (in_array('compactdisc', $formats)) {
				$format = 'compactdisc';
			} else if (in_array('figurative', $formats)) {
				$format = 'figurative';
			} else if (in_array('poster', $formats)) {
				$format = 'poster';
			} else if (in_array('file', $formats)) {
				$format = 'file';
			} else if (in_array('electronic', $formats)) {
				$format = 'electronic';
			} else if (in_array('game', $formats)) {
				$format = 'game';
			} else if (in_array('map', $formats)) {
				$format = 'map';
			} else {
				$format = 'unknown';
			}
		} else {
			// Default format. Overwrite below if other format is available.
			$format = 'book';
			if (isset($publicationTypeCode)) {
				if ($publicationTypeCode == 'm' || $publicationTypeCode == 's') {
					$format = 'book';
				} else if ($publicationTypeCode == 'n' || $publicationTypeCode == 't' || $publicationTypeCode == 'r') {
					$format = 'books';
				} else if ($publicationTypeCode == 'a') {
					$format = 'article';
				} else if ($publicationTypeCode == 'j' || $publicationTypeCode == 'p' || $publicationTypeCode == 'f') {
					$format = 'journal';
				} else if ($publicationTypeCode == 'z') {
					$format = 'newspaper';
				}
			}
		}
		
		// Preparing return array:
		$arr = [
				'contenttype'	=> $format,
				'author'		=> mb_substr($this->getPrimaryAuthor(), 0, 300, 'utf-8'),
				'callnumber'	=> $this->getCallNumber(),
				'size'			=> $size,
				'title'			=> mb_substr($this->getTitle(), 0, 300, 'utf-8')
		];
		if ($isbn = $this->getCleanISBN()) {
			$arr['isbn'] = $isbn;
		}
		if ($issn = $this->getCleanISSN()) {
			$arr['issn'] = $issn;
		}
		if ($oclc = $this->getCleanOCLCNum()) {
			$arr['oclc'] = $oclc;
		}
		if ($upc = $this->getCleanUPC()) {
			$arr['upc'] = $upc;
		}
		
		// If an ILS driver has injected extra details, check for IDs in there to fill gaps:
		if ($ilsDetails = $this->getExtraDetail('ils_details')) {
			foreach (['isbn', 'issn', 'oclc', 'upc'] as $key) {
				if (!isset($arr[$key]) && isset($ilsDetails[$key])) {
					$arr[$key] = $ilsDetails[$key];
				}
			}
		}
		
		return $arr;
	}
	
	
	
	// ######################################################################################
	// ################################# MULTI VOLUME WORKS #################################
	// ######################################################################################
	/**
	 * Get Solrfield parentSYS_str (SYS no. of parent record)
	 *
	 * @return string or null if empty
	 */
	public function getParentRecordSYS() {
		return isset($this->fields['parentSYS_str']) ? $this->fields['parentSYS_str'] : null;
	}
	
	/**
	 * Get Solrfield parentAC_str (AC no. of parent record)
	 *
	 * @return string or null if empty
	 */
	public function getParentRecordAC() {
		return isset($this->fields['parentAC_str']) ? $this->fields['parentAC_str'] : null;
	}

	/**
	 * Get Solrfield parentTitle_str (title of parent record)
	 *
	 * @return string or null if empty
	 */
	public function getParentRecordTitle() {
		return isset($this->fields['parentTitle_str']) ? $this->fields['parentTitle_str'] : null;
	}
	
	/**
	 * Get Solrfield childAC_str_mv (AC no(s). of all child records)
	 *
	 * @return array or null if empty
	 */
	public function getChildRecordAC() {
		return isset($this->fields['childAC_str_mv']) ? $this->fields['childAC_str_mv'] : null;
	}
	
	/**
	 * Get Solrfield childSYS_str_mv (SYS no(s). of all child records)
	 *
	 * @return array or null if empty
	 */
	public function getChildRecordSYS() {
		return isset($this->fields['childSYS_str_mv']) ? $this->fields['childSYS_str_mv'] : null;
	}
	
	/**
	 * Get Solrfield childTitle_str_mv (titles of all child records)
	 *
	 * @return array or null if empty
	 */
	public function getChildRecordTitle() {
		return isset($this->fields['childTitle_str_mv']) ? $this->fields['childTitle_str_mv'] : null;
	}

	/**
	 * Get Solrfield childVolumeNo_str_mv (titles of all child records)
	 *
	 * @return array or null if empty
	 */
	public function getChildVolumeNo() {
		return isset($this->fields['childVolumeNo_str_mv']) ? $this->fields['childVolumeNo_str_mv'] : null;
	}

	/**
	 * Get Solrfield childPublishDate_str_mv (publish date(s) of all child records)
	 *
	 * @return array or null if empty
	 */
	public function getChildPublishDate() {
		return isset($this->fields['childPublishDate_str_mv']) ? $this->fields['childPublishDate_str_mv'] : null;
	}

	/**
	 * Get Solrfield childEdition_str_mv (editions [Auflage] of all child records)
	 *
	 * @return array or null if empty
	 */
	public function getChildEdition() {
		return isset($this->fields['childEdition_str_mv']) ? $this->fields['childEdition_str_mv'] : null;
	}
	
	/**
	 * Get relevant information about all child records
	 *
	 * @return array or null if empty
	 */
	public function getChildRecords() {
		$childRecordSYSs = $this->getChildRecordSYS();
		$childRecordTitles = $this->getChildRecordTitle();
		$childVolumeNos = $this->getChildVolumeNo();
		$childPublishDates = $this->getChildPublishDate();
		$childEditions = $this->getChildEdition();
		
		$childRecords = null;
		
		foreach($childRecordSYSs as $key => $childRecordSYS) {
			$childVolumeNo = ($childVolumeNos[$key] == "0") ? null : $childVolumeNos[$key];
			$childTitle = ($childRecordTitles[$key] == "0") ? null : $childRecordTitles[$key];
			$childPublishDate = ($childPublishDates[$key] == "0") ? null : $childPublishDates[$key];
			$childEdition = ($childEditions[$key] == "0") ? null : $childEditions[$key];
			
			$childRecords[$childRecordSYS] = array(
					"volumeNo" => $childVolumeNo,
					"volumeTitle" => $childTitle,
					"volumePublishDate" => $childPublishDate,
					"volumeEdition" => $childEdition); 
		}
		
		// Create array for sorting
		foreach ($childRecords as $key => $rowToSort) {
			$volumeNo[$key] = $rowToSort['volumeNo'];
			$publishDate[$key] = $rowToSort['volumePublishDate'];
		}
		
		array_multisort($volumeNo, SORT_DESC, $publishDate, SORT_DESC, $childRecords);
		
		return $childRecords;
	}
	
	/**
	 * Check if the current record is has child records
	 *
	 * TODO: Ambiguous function name because this does not only check for volumes of
	 * multi-volume-works, but also for articles, serialvolumes and possible other child records.
	 * 
	 * @return boolean
	 */
	public function isMultiVolumeWork() {
		$childRecordSYSs = $this->getChildRecordSYS();
		$isMultiVolumeWork = ($childRecordSYSs == null) ?  false : true;
		return $isMultiVolumeWork;
	}
	
	
	
	// #######################################################################################
	// ##################################### PARENT INFO #####################################
	// #######################################################################################
	/**
	 * Get Solrfield childType_str_mv (gets the type of the child record, e. g. serial volume, article, etc.)
	 *
	 * @return array or null if empty
	 */
	public function getChildTypes() {
		return (isset($this->fields['childType_str_mv'])) ? $this->fields['childType_str_mv'] : null;
	}
	
	/**
	 * Check if record is a parent record with childs of type volumes (mutlivolume, serialvolume).
	 * Useful for displaying: volumes have different information (e. g. vol.-no., edition, etc) as articles.
	 *
	 * @return boolean
	 */
	public function isParentOfVolumes() {
		
		$isParentOfArticles = false;
		$arrChildTypes = $this->getChildTypes();
		if ($arrChildTypes != null) {
			$isParentOfArticles = (in_array('article', $arrChildTypes)) ? false : true;
		}
		return $isParentOfArticles;
	}
	
	/**
	 * Check if record is a parent record with childs of type "article"
	 * Useful for displaying: articles have different information (e. g. page-nos., etc) as volumes.
	 *
	 * @return boolean
	 */
	public function isParentOfArticles() {
		$isParentOfArticles = false;
		$arrChildTypes = $this->getChildTypes();
		if ($arrChildTypes != null) {
			$isParentOfArticles = (in_array('article', $arrChildTypes)) ? true : false;
		}
		return $isParentOfArticles;
	}
	
	
	// ######################################################################################
	// ##################################### CHILD INFO #####################################
	// ######################################################################################
	/**
	 * Check if record is a child volume (excluding articles)
	 *
	 * @return boolean
	 */
	public function isChildVolume() {
		$isChildVolume = false;
		$publicationTypeCode = $this->getPublicationTypeCode();
		$isArticle = (isset($publicationTypeCode) && $publicationTypeCode == 'a') ? true : false;
		$arrParentSYSs = $this->getParentSYSs();
		if ($isArticle == false && $arrParentSYSs != null) {
			$isChildVolume = true;
		}
		
		return $isChildVolume;
	}
	
	/**
	 * Get Solrfield parentSYS_str_mv (Aleph SYS no. of parent record(s))
	 *
	 * @return array or null if empty
	 */
	public function getParentSYSs() {
		return isset($this->fields['parentSYS_str_mv']) ? $this->fields['parentSYS_str_mv'] : null;
	}
	
	/**
	 * Get Solrfield parentTitle_str_mv (title of parent record(s))
	 *
	 * @return array or null if empty
	 */
	public function getParentTitles() {
		return isset($this->fields['parentTitle_str_mv']) ? $this->fields['parentTitle_str_mv'] : null;
	}
	

	// ######################################################################################
	// ################################### SERIAL VOLUMES ###################################
	// ######################################################################################
	/**
	 * Get Solrfield parentSeriesAC_str_mv (AC no. of parent series record(s))
	 *
	 * @return array or null if empty
	 */
	public function getParentSeriesACs() {
		return isset($this->fields['parentSeriesAC_str_mv']) ? $this->fields['parentSeriesAC_str_mv'] : null;
	}
	
	
	
	
	
	// #######################################################################################
	// #################################### PARENT SERIES ####################################
	// #######################################################################################
	/**
	 * Get Solrfield serialvolumeSYS_str_mv (SYS no. of serial volume(s))
	 *
	 * @return array or null if empty
	 */
	public function getSerialVolumeSYS() {
		return isset($this->fields['serialvolumeSYS_str_mv']) ? $this->fields['serialvolumeSYS_str_mv'] : null;
	}
	
	/**
	 * Get Solrfield serialvolumeAC_str_mv (AC no. of serial volume(s))
	 *
	 * @return array or null if empty
	 */
	public function getSerialVolumeAC() {
		return isset($this->fields['serialvolumeAC_str_mv']) ? $this->fields['serialvolumeAC_str_mv'] : null;
	}
	
	/**
	 * Get Solrfield serialvolumeTitle_str_mv (title of serial volume(s))
	 *
	 * @return array or null if empty
	 */
	public function getSerialVolumeTitle() {
		return isset($this->fields['serialvolumeTitle_str_mv']) ? $this->fields['serialvolumeTitle_str_mv'] : null;
	}
	
	/**
	 * Get Solrfield serialvolumeVolumeNo_str_mv (volume no. of serial volume(s))
	 *
	 * @return array or null if empty
	 */
	public function getSerialVolumeVolumeNo() {
		return isset($this->fields['serialvolumeVolumeNo_str_mv']) ? $this->fields['serialvolumeVolumeNo_str_mv'] : null;
	}
	
	/**
	 * Get Solrfield serialvolumeVolumeNoSort_str_mv (volume no. for sorting of serial volume(s))
	 *
	 * @return array or null if empty
	 */
	public function getSerialVolumeVolumeNoSort() {
		return isset($this->fields['serialvolumeVolumeNoSort_str_mv']) ? $this->fields['serialvolumeVolumeNoSort_str_mv'] : null;
	}
	
	/**
	 * Get Solrfield serialvolumeEdition_str_mv (edition (Auflage) of serial volume(s))
	 *
	 * @return array or null if empty
	 */
	public function getSerialVolumeEdition() {
		return isset($this->fields['serialvolumeEdition_str_mv']) ? $this->fields['serialvolumeEdition_str_mv'] : null;
	}
	
	/**
	 * Get Solrfield serialvolumePublishDate_str_mv (publish date of serial volume(s))
	 *
	 * @return array or null if empty
	 */
	public function getSerialVolumePublishDate() {
		return isset($this->fields['serialvolumePublishDate_str_mv']) ? $this->fields['serialvolumePublishDate_str_mv'] : null;
	}
	
	/**
	 * Get all relevant information about serial volumes
	 * 
	 * @return array
	 */
	public function getSerialVolumes() {
		$serialVolumeSYSs = $this->getSerialVolumeSYS();
		$serialVolumeTitles = $this->getSerialVolumeTitle();
		$serialVolumeNos = $this->getSerialVolumeVolumeNo();
		$serialVolumePublishDates = $this->getSerialVolumePublishDate();
		$serialVolumeEditions = $this->getSerialVolumeEdition();
	
		$serialVolumes = null;
	
		foreach($serialVolumeSYSs as $key => $serialVolumeSYS) {
			$serialVolumeNo = ($serialVolumeNos[$key] == "0") ? null : $serialVolumeNos[$key];
			$serialVolumeTitle = ($serialVolumeTitles[$key] == "0") ? null : $serialVolumeTitles[$key];
			$serialVolumePublishDate = ($serialVolumePublishDates[$key] == "0") ? null : $serialVolumePublishDates[$key];
			$serialVolumeEdition = ($serialVolumeEditions[$key] == "0") ? null : $serialVolumeEditions[$key];
				
			$serialVolumes[$serialVolumeSYS] = array(
					"volumeNo" => $serialVolumeNo,
					"volumeTitle" => $serialVolumeTitle,
					"volumePublishDate" => $serialVolumePublishDate,
					"volumeEdition" => $serialVolumeEdition);
		}
	
		// Create array for sorting
		foreach ($serialVolumes as $key => $rowToSort) {
			$volumeNo[$key] = $rowToSort['volumeNo'];
			$publishDate[$key] = $rowToSort['volumePublishDate'];
		}

		array_multisort($volumeNo, SORT_DESC, $publishDate, SORT_DESC, $serialVolumes);
		return $serialVolumes;
	}
	
	/**
	 * Check if record is a parent series record
	 * 
	 * @return boolean
	 */
	public function isSeries() {
		$serialVolumeSYSs = $this->getSerialVolumeSYS();
		$isSeries = ($serialVolumeSYSs == null) ? false : true;
		return $isSeries;
	}
	
	
	
	
	
	// ######################################################################################
	// ###################################### JOURNALS ######################################
	// ######################################################################################
	/**
	 * Check if record is a journal
	 *
	 * @return boolean
	 */
	public function isJournal() {
		$arrPublicationType = $this->getPublicationTypeFromCode();
		$strFortlaufendesWerk = (isset($arrPublicationType['fortlaufendesWerk'])) ? $arrPublicationType['fortlaufendesWerk'] : null;
		$boolIsJournal = ($strFortlaufendesWerk == 'Zeitschrift') ? true : false;
		return $boolIsJournal;
	}
	
	
	// ######################################################################################
	// ###################################### ARTICLES ######################################
	// ######################################################################################
	/**
	 * Check if record is an article
	 *
	 * @return boolean
	 */
	public function isArticle() {
		$publicationTypeCode = $this->getPublicationTypeCode();
		return (isset($publicationTypeCode) && $publicationTypeCode == 'a') ? true : false;
	}
	
	/**
	 * Get Solrfield articleParentAC_str (AC no. of parent record of the article)
	 *
	 * @return string or null if empty
	 */
	public function getArticleParentAC() {
		return isset($this->fields['articleParentAC_str']) ? $this->fields['articleParentAC_str'] : null;
	}
	
	/**
	 * Get Solrfield articleParentTitle_str (title of parent record of the article)
	 *
	 * @return string or null if empty
	 */
	public function getArticleParentTitle() {		
		return isset($this->fields['articleParentTitle_str']) ? $this->fields['articleParentTitle_str'] : null;
	}
	
	/**
	 * Get Solrfield articleParentVolumeNo_str (vol. no. of parent record of the article)
	 *
	 * @return string or null if empty
	 */
	public function getParentArticleVolumeNo() {
		return isset($this->fields['articleParentVolumeNo_str']) ? $this->fields['articleParentVolumeNo_str'] : null;
	}
	
	/*
	// TODO: What if not field 525 is used instead of 599?
	public function getArticleParentDetails() {	
		$returnValue = null;
		if ($this->getArticleParentAC() != null) { // It's an article or essay. (MAB field 599$-*$*)
			if ($this->getParentSYSs() != null) { // We have at least one SYS-No to which we can link
				// Get the titel of the parent record
				$parentTitle = (isset($this->getArticleParentTitle())) ? $this->getArticleParentTitle() : "k. A.";
				$parentVolumeNo = (isset($this->getParentArticleVolumeNo())) ? $this->getParentArticleVolumeNo() : null;				
				$returnValue = (isset($parentVolumeNo)) ? $parentTitle.' ('.$parentVolumeNo.')' : $parentTitle;
			} else { // We have 
				
			}
		} else if (isset()) { //  MAB field 525$**$a
		}
		return $returnValue;
	}
	*/
	
	
	// #####################################################################################
	// ################################# PUBLICATION TYPES #################################
	// #####################################################################################
	
	/**
	 * Get Solrfield begrenzteWerke_str (value of MAB field 051)
	 *
	 * @return string or null if empty
	 */
	public function getBegrenzteWerke() {
		return isset($this->fields['begrenzteWerke_str']) ? $this->fields['begrenzteWerke_str'] : null;
	}
	
	/**
	 * Get Solrfield begrenzteWerke_str (value of MAB field 052)
	 *
	 * @return string or null if empty
	 */
	public function getFortlaufendeWerke() {
		return isset($this->fields['fortlaufendeWerke_str']) ? $this->fields['fortlaufendeWerke_str'] : null;
	}
	
	/**
	 * Get Solrfield erscheinungsform_str (based on first character of fields 051 and 052)
	 * The value of this field is generated while importing records with AkImporter.
	 *
	 * @return string or null if empty
	 */
	public function getPublicationTypeFromIndex() {
		return isset($this->fields['erscheinungsform_str']) ? $this->fields['erscheinungsform_str'] : null;
	}
	
	/**
	 * Get publication type code (first character of field 051 or 052)
	 *
	 * @return string or null if empty
	 */
	public function getPublicationTypeCode() {
		$publicationTypeCode = null;
		$begrenztesWerk = $this->getBegrenzteWerke();
		$fortlaufendesWerk = $this->getFortlaufendeWerke();
		
		if($begrenztesWerk != null) {
			$publicationTypeCode = substr($begrenztesWerk[0], 0, 1); // Get first character of the string
		}
		
		if($fortlaufendesWerk != null) {
			$publicationTypeCode = substr($fortlaufendesWerk[0], 0, 1); // Get first character of the string
		}
		
		return $publicationTypeCode;
	}
	
	/**
	 * Get type of publication by publication type code (first character of field 051 or 052)
	 *
	 * TODO: Check if this function is still needed.
	 * 
	 * @return string or null if empty
	 */
	public function getPublicationTypeFromCode() {
		$publicationType = null;
		$begrenztesWerk = $this->getBegrenzteWerke();
		$fortlaufendesWerk = $this->getFortlaufendeWerke();
		
		// Field 051
		if($begrenztesWerk != null) {
			
			// Get first character of field 051:
			$publicationTypeCode = substr($begrenztesWerk[0], 0, 1);
			
			switch ($publicationTypeCode) {
				case 'a':
					$publicationType['begrenztesWerk'] = 'unselbständig erschienenes Werk';
					break;
				case 'f':
					$publicationType['begrenztesWerk'] = 'Fortsetzung';
					break;
				case 'm':
					$publicationType['begrenztesWerk'] = 'einbändiges Werk - nicht Teil eines Gesamtwerks';
					break;
				case 'n':
					$publicationType['begrenztesWerk'] = 'mehrbändiges begrenztes Werk - nicht Teil eines Gesamtwerks';
					break;
				case 's':
					$publicationType['begrenztesWerk'] = 'einbändiges Werk und Teil (mit Stücktitel) eines Gesamtwerks';
					break;
				case 't':
					$publicationType['begrenztesWerk'] = 'mehrbändges begrenztes Werk und Teil (mit Stücktitel) eines Gesamtwerks';
					break;
			}
		}
		
		// Field 052
		if($fortlaufendesWerk != null) {
			
			// Get first character of field 052:
			$fortlaufendesWerkCode = substr($fortlaufendesWerk[0], 0, 1);
			
			switch ($fortlaufendesWerkCode) {
				case 'a':
					$publicationType['fortlaufendesWerk'] = 'unselbständig erschienenes Werk';
					break;
				case 'f':
					$publicationType['fortlaufendesWerk'] = 'Fortsetzung';
					break;
				case 'j':
					$publicationType['fortlaufendesWerk'] = 'zeitschriftenartige Reihe';
					break;
				case 'p':
					$publicationType['fortlaufendesWerk'] = 'Zeitschrift';
					break;
				case 'r':
					$publicationType['fortlaufendesWerk'] = 'Schriftenreihe (Serie)';
					break;
				case 'z':
					$publicationType['fortlaufendesWerk'] = 'Zeitung';
					break;
			}
		}
		
		return $publicationType;
	}

	
	// #######################################################################################
	// ################################# PUBLICATION DETAILS #################################
	// #######################################################################################
	/**
	 * Get Solrfield publisher
	 *
	 * @return array or null if empty
	 */
	public function getPublisherNames() {
		return isset($this->fields['publisher']) ? $this->fields['publisher'] : null;
	}
	
	/**
	 * Get Solrfield publishDate or datePublishSort_str
	 *
	 * @return string or null if empty
	 */
	public function getPublishDate() {
		$arrPublishDates = isset($this->fields['publishDate']) ? $this->fields['publishDate'] : null;
		$strPublishDateSort = isset($this->fields['datePublishSort_str']) ? $this->fields['datePublishSort_str'] : null;
		
		if ($arrPublishDates != null) {
			$publishDates = implode(', ', $arrPublishDates);
		} else if ($strPublishDateSort != null) {
			$publishDates = $strPublishDateSort;
		} else {
			$publishDates = null;
		}
		return $publishDates;
	}
	
	/**
	 * Get Solrfield datePublishFirst_str (e. g. first publish date of a journal)
	 *
	 * @return string or null if empty
	 */
	public function getDateFirstPublished() {
		return isset($this->fields['datePublishFirst_str']) ? $this->fields['datePublishFirst_str'] : null;
	}
	
	/**
	 * Get Solrfield datePublishLast_str (e. g. last publish date of a journal)
	 *
	 * @return string or null if empty
	 */
	public function getDateLastPublished() {
		return isset($this->fields['datePublishLast_str']) ? $this->fields['datePublishLast_str'] : null;
	}
	
	/**
	 * Get first and last date (if available) for displaying the publicaion span.
	 * Examples:
	 * 	1980 - 2005
	 * 	2010 -
	 *
	 * @return string or null if empty
	 */
	public function getFirstLastDatePublished() {
		$dateFirst = $this->getDateFirstPublished();
		$dateLast = $this->getDateLastPublished();
		
		if (isset($dateFirst) && isset($dateLast)) {
			return $dateFirst.' - '.$dateLast;
		} else if (isset($dateFirst)) {
			return $dateFirst.' - ';
		} else {
			return null;
		}
		return null;
	}
	
	/**
	 * Get Solrfield dateSpan (e. g. publish dates [from - to] of a journal)
	 * 
	 * TODO: Check if this function is necessary (we already have a similar one with getFirstLastDatePublished())
	 *       This function works with the original VuFind Solrfield "dateSpan".
	 *       
	 * @return array or null if empty
	 */
	public function getPublicationHistory() {
		return isset($this->fields['dateSpan'][0]) ? $this->fields['dateSpan'][0] : null;
	}
	
	/**
	 * Get Solrfield pubFrequency_str (e. g. weekly, monthly, etc.)
	 *
	 * @return string or null if empty
	 */
	public function getFrequencyOfPublication() {
		return isset($this->fields['pubFrequency_str']) ? $this->fields['pubFrequency_str'] : null;
	}
	
	/**
	 * Get Solrfield issn
	 *
	 * @return array or null if empty
	 */
	public function getIssns() {
		return isset($this->fields['issn']) ? $this->fields['issn'] : null;
	}

	/**
	 * Get Solrfield publishPlace_str
	 *
	 * @return string or null if empty
	 */
	public function getPublicationPlace() {
		return isset($this->fields['publishPlace_str']) ? $this->fields['publishPlace_str'] : null;
	}

	/**
	 * Get Solrfield title_alt (alternative title)
	 * 
	 * TODO: Check if we should return null if field is empty instead of an empty array.
	 * 
	 * @return array
	 */
	public function getFurtherTitles() {
		return isset($this->fields['title_alt']) ? $this->fields['title_alt'] : array();
	}
	
	/**
	 * Get Solrfield author
	 * 
	 * TODO: Check if we should return null if field is empty instead of an empty string.
	 * 
	 * @return string
	 */
	public function getPrimaryAuthor() {
		
		// Primary author is set
		if (isset($this->fields['author'])) {
			return $this->fields['author'];
		}
		
		// If no primary author is set, check for "corporate author"
		if (isset($this->fields['corporateAuthorName_txt'])) {
			return $this->fields['corporateAuthorName_txt'];
		}
		if (isset($this->fields['corporateAuthorName_txt'])) {
			return $this->fields['corporateAuthorName_txt'];
		}
		
		// If no primary author is set, check for "responsability note"
		if (isset($this->fields['responsibilityNote_txt'])) {
			return $this->fields['responsibilityNote_txt'];
		}
		
		// If we got this far, no apropriate author field is set
		return '';
		
		// Original:
		//return (isset($this->fields['author'])) ? $this->fields['author'] : '';
	}
	
	
	/**
	 * Get Solrfield author2 (secondary authors)
	 *
	 * TODO: Check if we should return null if field is empty instead of an empty array.
	 *
	 * @return array
	 */
	public function getSecondaryAuthors() {
		
		// Field author2 author is set
		if (isset($this->fields['author2'])) {
			return $this->fields['author2'];
		}
		
		// Field corporateAuthor2NameGnd_txt_mv author is set
		if (isset($this->fields['corporateAuthor2NameGnd_txt_mv'])) {
			return $this->fields['corporateAuthor2NameGnd_txt_mv'];
		}
		
		// Field corporateAuthor2GndNo_str_mv author is set
		if (isset($this->fields['corporateAuthor2GndNo_str_mv'])) {
			return $this->fields['corporateAuthor2GndNo_str_mv'];
		}
		
		// If we got this far, no apropriate author field is set
		return array();
		
		// Original:
		//return isset($this->fields['author2']) ? $this->fields['author2'] : array();
	}
	
	/**
	 * Get all authors
	 *
	 * @return array or null if empty
	 */
	public function getAuthors() {
		$autor = isset($this->fields['author']) ? $this->fields['author'] : array();
		$author2 = isset($this->fields['author2']) ? $this->fields['author2'] : array();
		$author_additional = isset($this->fields['author_additional']) ? $this->fields['author_additional'] : array();
		
		$authorsAll = array_merge((array)$autor, $author2, $author_additional);
		
		return isset($authorsAll) ? $authorsAll : null;
	}
	
	/**
	 * Get all all involved / participants incl. their role and authoirty no.
	 * 
	 *  @return array or null if empty
	 */
	public function getParticipants() {
		
		// First participant
		$author1 = isset($this->fields['author']) ? $this->fields['author'] : null;
		$author1Role = isset($this->fields['author_role']) ? $this->fields['author_role'] : 'NoRole';
		$author1Gnd = isset($this->fields['author_GndNo_str']) ? $this->fields['author_GndNo_str'] : 'NoGndId';
		
		// Second participant
		$author2 = isset($this->fields['author2']) ? $this->fields['author2'][0] : null;
		$author2Role = isset($this->fields['author2_role']) ? $this->fields['author2_role'][0] : 'NoRole';
		$author2Gnd = isset($this->fields['author2_GndNo_str']) ? $this->fields['author2_GndNo_str'] : 'NoGndId';
		
		// All other participants
		$author_additional_NameRoleGnd = (isset($this->fields['author_additional_NameRoleGnd_str_mv']) && !empty($this->fields['author_additional_NameRoleGnd_str_mv'])) ? $this->fields['author_additional_NameRoleGnd_str_mv'] : null;
		
		// First Corporate participant
		$corp1 = isset($this->fields['corporateAuthorName_txt']) ? $this->fields['corporateAuthorName_txt'] : null;
		$corp1Role = isset($this->fields['corporateAuthorRole_str']) ? $this->fields['corporateAuthorRole_str'] : 'NoRole';
		$corp1Gnd = isset($this->fields['corporateAuthorGndNo_str']) ? $this->fields['corporateAuthorGndNo_str'] : 'NoGndId';
		
		// All other corporate participants
		$corp_additional_NameRoleGnd = (isset($this->fields['corporateAuthor2NameRoleGnd_str_mv']) && !empty($this->fields['corporateAuthor2NameRoleGnd_str_mv']))? $this->fields['corporateAuthor2NameRoleGnd_str_mv'] : null;
		
		
		$participants = [];
		if ($author1 != null && $author1Role != null && $author1Gnd != null) {
			$participants[$author1Role][] = array($author1Gnd => $author1);
		}
		if ($author2 != null && $author2Role != null && $author2Gnd != null) {
			$participants[$author2Role][] = array($author2Gnd => $author2);
		}
		if ($corp1 != null && $corp1Role != null && $corp1Gnd != null) {
			$participants[$corp1Role][] = array($corp1Gnd => $corp1);
		}
		
		if ($author_additional_NameRoleGnd != null) {
    		foreach ($author_additional_NameRoleGnd as $key => $value) {
    			
    			if (($key % 3) == 0) { // First of 3 values
    				$name = $author_additional_NameRoleGnd[$key];
    			} else if (($key % 3) == 1) { // Second of 3 values
    				$role = $author_additional_NameRoleGnd[$key];
    			}  else if (($key % 3) == 2) { // Third and last of 3 values
    				$gnd = $author_additional_NameRoleGnd[$key];
    				
    				// We have all values now, add them to the return array:
    				$participants[$role][] = array($gnd => $name);
    			}
    		}
    	}
    	
    	if ($corp_additional_NameRoleGnd != null) {
    		foreach ($corp_additional_NameRoleGnd as $key => $value) {
    			 
    			if (($key % 3) == 0) { // First of 3 values
    				$name = $corp_additional_NameRoleGnd[$key];
    			} else if (($key % 3) == 1) { // Second of 3 values
    				$role = $corp_additional_NameRoleGnd[$key];
    			}  else if (($key % 3) == 2) { // Third and last of 3 values
    				$gnd = $corp_additional_NameRoleGnd[$key];
    	
    				// We have all values now, add them to the return array:
    				$participants[$role][] = array($gnd => $name);
    			}
    		}
    	}
			
		return (isset($participants) && !empty($participants)) ? $participants : null;
	}
	
	
	/**
	 * Get all corporate authors
	 *
	 * @return array or null if empty
	 */
	public function getCorporateAuthors() {
		$corp = isset($this->fields['corporateAuthorName_str']) ? $this->fields['corporateAuthorName_str'] : array();
		$corp2Gnd = isset($this->fields['corporateAuthor2NameGnd_str_mv']) ? $this->fields['corporateAuthor2NameGnd_str_mv'] : array();
		$corp2 = isset($this->fields['corporateAuthor2Name_str_mv']) ? $this->fields['corporateAuthor2Name_str_mv'] : array();
		
		$corpAll = array_merge((array)$corp, $corp2Gnd, $corp2);

		return isset($corpAll) ? $corpAll : null;
	}
	
	
	/**
	 * Get all subjects
	 *
	 * @return array
	 */
	public function getAllSubjectHeadings() {		
		
		$headings = [];
		foreach (['topic', 'authHeadingSubject_txt_mv', 'authUseForSubject_txt_mv', 'geographic', 'authHeadingGeographic_txt_mv', 'authUseForGeographic_txt_mv', 'authHeadingCongress_txt_mv', 'authUseForCongress_txt_mv', 'authHeadingWork_txt_mv', 'authUseForWork_txt_mv', 'genre', 'era'] as $field) {
			if (isset($this->fields[$field])) {
				$headings = array_merge($headings, $this->fields[$field]);
			}
		}
		
		sort($headings, SORT_ASC);
	
		// The Solr index doesn't currently store subject headings in a broken-down
		// format, so we'll just send each value as a single chunk.  Other record
		// drivers (i.e. MARC) can offer this data in a more granular format.
		$callback = function ($i) {
			return [$i];
		};
		return array_map($callback, array_unique($headings));
	}
	
	


	// #######################################################################################
	// ################################## ILS COMMUNICATION ##################################
	// #######################################################################################
	/**
     * Attach an ILS connection and related logic to the driver
     * 
     * Adopted from VuFind\RecordDriver\SolrMarc.
     * 
     * @param \VuFind\ILS\Connection       $ils            ILS connection
     * @param \VuFind\ILS\Logic\Holds      $holdLogic      Hold logic handler
     * @param \VuFind\ILS\Logic\TitleHolds $titleHoldLogic Title hold logic handler
     *
     * @return void
     */
    public function attachILS(\VuFind\ILS\Connection $ils, \VuFind\ILS\Logic\Holds $holdLogic, \VuFind\ILS\Logic\TitleHolds $titleHoldLogic) {
    	$this->ils = $ils;
        $this->holdLogic = $holdLogic;
        $this->titleHoldLogic = $titleHoldLogic;
    }
    

    /**
     * Do we have an attached ILS connection?
     * 
     * Adopted from VuFind\RecordDriver\SolrMarc.
     * 
     * @return bool
     */
    public function hasILS() {
        return null !== $this->ils;
    }
    
    
    /**
     * Get the bibliographic level of the current record.
     * 
     * Adopted from VuFind\RecordDriver\SolrMarc.
     * 
     * @return string
     */
    public function getBibliographicLevel() {
    	$leader = $this->getLeader();
    	$biblioLevel = strtoupper($leader[6]);
    	
    	switch ($biblioLevel) {
    		case 'M': // Monograph
    			return "Monograph";
    		case 'S': // Serial
    			return "Serial";
    		case 'A': // Monograph Part
    			return "MonographPart";
    		case 'B': // Serial Part
    			return "SerialPart";
    		case 'C': // Collection
    			return "Collection";
    		case 'D': // Collection Part
    			return "CollectionPart";
    		default:
    			return "Unknown";
    	}
    }
    
    

    
    /**
     * Get an array of information about record holdings, obtained in real-time from the ILS.
     * Mask call nos and collections if value set in AKsearch.ini
     * 
     * Adopted from VuFind\RecordDriver\SolrMarc.
     * 
     * @return array
     */
    public function getRealTimeHoldings() {
    	// Get real time holdings
    	if (!$this->hasILS()) {
    		return array();
    	}
    	try {
    		$holdings = $this->holdLogic->getHoldings($this->getSysNo());
    		
    		// Masking call no 1, call no 2, collection and collection description
    		foreach ($holdings as &$holdingsOfLocation) {
    			$items = &$holdingsOfLocation['items'];
    			foreach ($items as &$item) {
    				$callNo1 = (isset($item['callnumber']) && !empty($item['callnumber'])) ? $item['callnumber'] : null;
    				$callNo2 = (isset($item['callnumber_second']) && !empty($item['callnumber_second'])) ? $item['callnumber_second'] : null;
    				$collection = (isset($item['collection']) && !empty($item['collection'])) ? $item['collection'] : null;
    				$collection_desc = (isset($item['collection_desc']) && !empty($item['collection_desc'])) ? $item['collection_desc'] : null;
    				
    				$item['callnumber'] = ($callNo1 != null) ? $this->getMaskedValue($callNo1) : null;
    				$item['callnumber_second'] = ($callNo2 != null) ? $this->getMaskedValue($callNo2) : null;
    				$item['collection'] = ($collection != null) ? $this->getMaskedValue($collection) : null;
    				$item['collection_desc'] = ($collection_desc != null) ? $this->getMaskedValue($collection_desc) : null;
    			}
    		}
    		
    		return $holdings;
    	} catch (ILSException $e) {
    		return array();
    	}
    }

    private function getMaskedValue($stringToMask, $mode = 'begins') {
    	
    	if ($mode == 'begins') {
    		$akConfigMasking = trim($this->akConfig->Masking->beginswith);
    		 
    		// Masking call no 1, call no 2, collection and collection description
    		if (isset($akConfigMasking) && !empty($akConfigMasking)) {
    			$arrBeginswith = array_reverse(explode(',', $akConfigMasking));
    			$beginswithExceptions = trim($this->akConfig->Masking->beginswithExceptions);
    			$arrBeginswithExceptions = array_reverse(explode(',', $beginswithExceptions));
    		
    			foreach ($arrBeginswith as $beginswith) {
    				$beginswith = trim($beginswith);
    				 
    				if (isset($beginswithExceptions) && !empty($beginswithExceptions)) {
    					foreach ($arrBeginswithExceptions as $beginswithException) {
    						$beginswithException = trim($beginswithException);
    						if (substr($stringToMask, 0, strlen($beginswithException)) == $beginswithException) {
    							return $stringToMask;
    						}
    					}
    				}
    		
    				if (substr($stringToMask, 0, strlen($beginswith)) == $beginswith) {
    					return $beginswith.preg_replace("/./", '*', substr($stringToMask, strlen($beginswith), strlen($stringToMask)));
    				}
    			}
    		}
    	} else if ($mode == 'all') {
    		return preg_replace("/./", '*', substr($stringToMask, 0, strlen($stringToMask)));
    	}
    	
    	
    	return $stringToMask;
    }

    /**
     * Get an array of information about record history, obtained in real-time from the ILS.
     * 
     * Adopted from VuFind\RecordDriver\SolrMarc.
     * 
     * @return array
     */
    public function getRealTimeHistory()
    {
        // Get Acquisitions Data
        if (!$this->hasILS()) {
            return array();
        }
        try {
            return $this->ils->getPurchaseHistory($this->getSysNo());
        } catch (ILSException $e) {
            return array();
        }
    }

    /**
     * Get a link for placing a title level hold.
     * 
     * Adopted from VuFind\RecordDriver\SolrMarc.
     *  
     * @return mixed A url if a hold is possible, boolean false if not
     */
    public function getRealTimeTitleHold() {
        if ($this->hasILS()) {
            $biblioLevel = strtolower($this->getBibliographicLevel());
            if ("monograph" == $biblioLevel || strstr("part", $biblioLevel)) {
                if ($this->ils->getTitleHoldsMode() != "disabled") {
                    return $this->titleHoldLogic->getHold($this->getSysNo());
                }
            }
        }
        return false;
    }
	
    /**
     * Gets the status of the record (available or unavailable) from the ILS driver.
     * See also getStatus() function in \VuFind\ILS\Driver\Aleph
     * 
     * @param string $id The record id to retrieve the holdings for
     *
     * @throws ILSException
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    public function getStatus() {
    	if ($this->hasILS()) {
    		return $this->ils->getStatus($this->getSysNo());
    	}
    }
    
    /**
     * Gets the Aleph journal holdings
     * 
     * @return array
     */
    public function getJournalHoldings() {    	
    	if (!$this->hasILS()) {
    		return array();
    	}
    	try {
    		return $this->ils->getJournalHoldings($this->getSysNo());
    	} catch (ILSException $e) {
    		return array();
    	}
    }
    
    /**
     * Gets the sublibrary name by sublibrary code
     * 
     * @return string
     */
    public function getSubLibraryName($subLibCode) {
    	return $this->ils->getSubLibName($subLibCode);
    }

    /**
     * Gets the URLs available for the record (e. g. to table of contents, etc.)
     *
     * @return array
     */
    public function getURLs() {
    	
    	$retVal = [];
    	
    	if (isset($this->fields['url']) && is_array($this->fields['url'])) {
    		$urls =  $this->fields['url'];
    	}
    	
    	if (isset($urls)) {
    		$counter = 0;
    		foreach ($urls as $key => $value) {
    			
    			if (($key % 3) == 0) { // First of 3 values
    				$url = $urls[$key];
    			} else if (($key % 3) == 1) { // Second of 3 values
    				$desc = $urls[$key];
    			}  else if (($key % 3) == 2) { // Third and last of 3 values
    				$mime = $urls[$key];
    				
    				// We have all values now, add them to the return array:
    				$counter = $counter + 1;
    				$retVal[$counter]['url'] = $url;
    				$retVal[$counter]['desc'] = $desc;
    				$retVal[$counter]['mime'] = $mime;
    			}
    		}
    	}
    	
   		return $retVal;
    }
    
}
?>
