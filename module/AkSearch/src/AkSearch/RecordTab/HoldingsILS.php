<?php
/**
 * Tab for library holdings of currently loaded record.
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
 * @package  RecordTabs
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://wien.arbeiterkammer.at/service/bibliothek/
 */

namespace AkSearch\RecordTab;
use VuFind\RecordTab\AbstractBase as AbstractBase;


class HoldingsILS extends AbstractBase {


	private $hasItemHoldings = false;
	private $hasJournalHoldings = false;
	private $description = 'Holdings';
	
    /**
     * Return value is title of the Tab.
     * Is a method declared in interface TabInterface.php
     *
     * @return string
     */
    public function getDescription() {
    	//return $this->description;
    	//return 'Holdings';
    	return 'Bestand';
    	
    	// Return "Online" if it's an electronic record. But this would be redundant.
    	/*
    	$formats = $this->getRecordDriver()->tryMethod('getFormats');
    	$format = ($formats != null && count($formats) == 1) ? $formats[0] : null;
    	if ($format != 'electronic') {
        	return 'Holdings';
    	} else {
    		return 'Online';
    	}
    	*/
    }


    /**
     * Return value declares if Tab is active.
     * Is a method declared in interface TabInterface.php
     *
     * @return bool
     */
    public function isActive() {
    	$isActive = false;
    	
    	// If there are any journal holdings or item holdings, and the record is not electronic, show the tab
    	$formats = $this->getRecordDriver()->tryMethod('getFormats');
    	$format = ($formats != null && count($formats) == 1) ? $formats[0] : null;
    	if ($format != 'electronic') {
    		//$hasIlsHoldings = $this->getRecordDriver()->tryMethod('hasIlsHoldings');
    		//$hasJournalHoldings = $this->getRecordDriver()->tryMethod('hasJournalHoldings');
    		$this->setHasItemHoldings($this->getRecordDriver()->tryMethod('hasIlsHoldings'));
    		$this->setHasJournalHoldings($this->getRecordDriver()->tryMethod('hasJournalHoldings'));
    		if ($this->hasItemHoldings() || $this->hasJournalHoldings()) {
    			$isActive = true;
    		}
    	} else {
    		// Activate tab to show URLs to online sources
    		$isActive = true;
    	}
    	
    	
    	/*
    	$formats = $this->getRecordDriver()->tryMethod('getFormats');
    	$format = ($formats != null && count($formats) == 1) ? $formats[0] : null;
    	if ($format != 'electronic') {
    		// If it is a journal or if the record has child records, we first test if there are ILS holdings before we show the tab
    		$isJournal = $this->getRecordDriver()->tryMethod('isJournal');
    		$isParentOfVolumes = $this->getRecordDriver()->tryMethod('isParentOfVolumes');
    		$isParentOfArticles = $this->getRecordDriver()->tryMethod('isParentOfArticles');
    		if ($isJournal || $isParentOfVolumes || $isParentOfArticles) {
    			$hasIlsHoldings = $this->getRecordDriver()->tryMethod('hasIlsHoldings');
    			if ($hasIlsHoldings) {
    				if ($isJournal) {
    					$this->setDescription('Bindeeinheiten');
    				}
    				$isActive = true;
    			}
    		} else {
    			$isActive = true;
    		}
    	} else {
    		// Activate tab to show URLs to online sources
    		$isActive = true;
    	}
    	*/
    	
    	
    	/*
    	// If is a "Fortlaufendes Werk" we have to check if there are holdings.
    	if (! empty($this->getRecordDriver()->tryMethod('getFortlaufendeWerke'))) {
    		$hasJournalHoldings = $this->getRecordDriver()->tryMethod('hasJournalHoldings');
    		if ($hasJournalHoldings) {
    			$tabEnabled = true;
    		}
    	}
		*/
    	
        return $isActive;
    }
    

    public function hasItemHoldings() {
    	return $this->hasItemHoldings;
    }
    
    public function setHasItemHoldings($hasItemHoldings = false) {
    	$this->hasItemHoldings = $hasItemHoldings;
    }
    
    public function hasJournalHoldings() {
    	return $this->hasJournalHoldings;
    }
    
    public function setHasJournalHoldings($hasJournalHoldings = false) {
    	$this->hasJournalHoldings = $hasJournalHoldings;
    }
    
    private function setDescription($description) {
    	$this->description = $description;
    }
}
?>