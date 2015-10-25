<?php
/**
 * Tab for showing child records (volumes, articles) of the currently loaded parent record.
 * 
 * TODO: Rename to a more generic name because this Tab is not only shown for "Multi Volume Works" but also for Series.
 * 		 It could be reused for other "parent - child" constructions.
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

class MultiVolumeWorks extends AbstractBase {
    
    /**
     * Title of the Tab. Defauts to "volumes"
     * 
     * @var string
     */
    private $tabTitle = 'volumes';

    
    /**
     * Return value is title of the Tab.
     * Is a method declared in interface TabInterface.php
     *
     * @return string
     */
    public function getDescription()
    {
    	// Check if currently loaded record is a parent of volumes or of articles (type of child records is important)
    	$isParentOfVolumes = $this->getRecordDriver()->tryMethod('isParentOfVolumes');
		$isParentOfArticles = $this->getRecordDriver()->tryMethod('isParentOfArticles');
		
		// Set title of Tab according to the type of child records
    	if ($isParentOfVolumes) {
    		$this->setDescription('volumes');
    	} else if ($isParentOfArticles) {
    		$this->setDescription('contains');
    	}
    	
    	return $this->tabTitle;
    }
    
    /**
     * Set the title of Tab.
     * 
     * @param string $tabTitle	Title of the Tab
     */
    public function setDescription($tabTitle) {
    	$this->tabTitle = $tabTitle;
    }

    /**
     * Return value declares if Tab is active.
     * Is a method declared in interface TabInterface.php
     * 
     * @return bool
     */
    public function isActive() {
    	// Check if currently loaded record is a parent of volumes or of articles (type of child records is important)
    	$isParentOfVolumes = $this->getRecordDriver()->tryMethod('isParentOfVolumes');
		$isParentOfArticles = $this->getRecordDriver()->tryMethod('isParentOfArticles');
		
		// If currently loaded record is a parent of volumes or of articles, show the Tab
		if ($isParentOfVolumes || $isParentOfArticles) {
			$tabEnabled = true;
		} else {
			$tabEnabled = false;
		}
		
		// Tab is only visible if this method returns true
		return $tabEnabled;
    }

}
?>
