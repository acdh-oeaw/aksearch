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

class SolrMab extends SolrDefault {

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
		$strFortlaufendesWerk = $arrPublicationType['fortlaufendesWerk'];
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
	 * Get Solrfield publishDate
	 *
	 * @return array or null if empty
	 */
	public function getPublishDate() {
		$arrPublishDates = isset($this->fields['publishDate']) ? $this->fields['publishDate'] : null;

		if ($arrPublishDates != null) {
			$publishDates = implode(', ', $arrPublishDates);
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
		return isset($this->fields['author']) ? $this->fields['author'] : '';
	}
	
	/**
	 * Get Solrfield author2 (secondary authors)
	 *
	 * TODO: Check if we should return null if field is empty instead of an empty array.
	 *
	 * @return array
	 */
	public function getSecondaryAuthors() {
		return isset($this->fields['author2']) ? $this->fields['author2'] : array();
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
     * 
     * Adopted from VuFind\RecordDriver\SolrMarc.
     * 
     * @return array
     */
    public function getRealTimeHoldings()
    {
    	// Get real time holdings
    	if (!$this->hasILS()) {
    		return array();
    	}
    	try {
    		return $this->holdLogic->getHoldings($this->getSysNo());
    	} catch (ILSException $e) {
    		return array();
    	}
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
    	$arrHolResult = $this->ils->getJournalHoldings($this->getSysNo());
    	return $arrHolResult;
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
    	
    	if (isset($this->fields['urlText_str_mv']) && is_array($this->fields['urlText_str_mv'])) {
    		$descs = $this->fields['urlText_str_mv'];
    	}
    	
    	foreach ($urls as $key => $value) {
    		$retVal[] = ['url' => $urls[$key], 'desc' => $descs[$key]];
    	}
    	
    	return $retVal;
	}

}
?>