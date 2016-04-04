<?php
/**
 * Search box view helper for Akfilter search.
 *
 * PHP version 5
 *
 * Copyright (C) AK Bibliothek Wien 2016.
 * Overriding some functions from extended original:
 * @see VuFind\View\Helper\Root\SearchBox
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
 * @package  View_Helper_Root
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://wien.arbeiterkammer.at/service/bibliothek/
 */

namespace AkSearch\View\Helper\Root;
use VuFind\Search\Options\PluginManager as OptionsManager;

class SearchBox extends \VuFind\View\Helper\Root\SearchBox {

    /**
     * Support method for getHandlers() -- load combined settings.
     * 
     * Extended for the use of Akfilter search option. This allows a prefiltered
     * view on the Solr index with combined search handlers
     *
     * @param string $activeSearchClass Active search class ID
     * @param string $activeHandler     Active search handler
     *
     * @return array
     */
    protected function getCombinedHandlers($activeSearchClass, $activeHandler) {
    	
        // Build settings:
        $handlers = [];
        $selectedFound = false;
        $backupSelectedIndex = false;
        $settings = $this->getCombinedHandlerConfig($activeSearchClass);
        $typeCount = count($settings['type']);
        for ($i = 0; $i < $typeCount; $i++) {
            $type = $settings['type'][$i];
            $target = $settings['target'][$i];
            $label = $settings['label'][$i];
            
            if ($type == 'VuFind') {
                $options = $this->optionsManager->get($target);
                $j = 0;
                $basic = $options->getBasicHandlers();
                if (empty($basic)) {
                    $basic = ['' => ''];
                }

				// Set some variables to avoid PHP Warnings:
                $prevAkfilterKey = null;
                $akfilterKey = null;
                $akfilterValue = null;
                
                foreach ($basic as $searchVal => $searchDesc) {
                	$j++;
                	
                	// Check if search target is Akfilter
                	if ($target == 'Akfilter') {

                		// Separate the search key and the search value:
                		if (strpos($searchVal, ':') !== false) {
                			$akfilterKey = explode(":", $searchVal)[0];
                			$akfilterValue = explode(":", $searchVal)[1];
                		}
                		
                		// Set selected search value (important for DropDown of combined search handler)
                		$selected = $target == $activeSearchClass && $activeHandler == $akfilterValue;
                		if ($selected) {
                			$selectedFound = true;
                		} else if ($backupSelectedIndex === false && $target == $activeSearchClass) {
                			$backupSelectedIndex = count($handlers);
                		}
                		
                		// Indent all search labels except if it's search key is different from the previous one.
                		// That means that a new filtered search type begins in the DropDown. The first handler of
                		// this new search type should not be indented.
                		$indent = true;
                		if ($prevAkfilterKey != $akfilterKey) {
                			$indent = false;
                		}
                		
                		// Set the value for the previous search key:
                		$prevAkfilterKey = $akfilterKey;
                		
                		$handlers[] = [
                				'value' => $type . ':' . $target . '|' . $akfilterValue,
                				'label' => $searchDesc,
                				'indent' => $indent,
                				'selected' => $selected
                		];
                	} else { // The standard proceture if the search target is not Akfilter
                		//$j++;
                		$selected = $target == $activeSearchClass && $activeHandler == $searchVal;
                		if ($selected) {
                			$selectedFound = true;
                		} else if ($backupSelectedIndex === false && $target == $activeSearchClass) {
                			$backupSelectedIndex = count($handlers);
                		}
                		
                		$handlers[] = [
                				'value' => $type . ':' . $target . '|' . $searchVal,
                				'label' => $j == 1 ? $label : $searchDesc,
                				'indent' => $j == 1 ? false : true,
                				'selected' => $selected
                		];
                	}
                }
                
            } else if ($type == 'External') {
                $handlers[] = [
                    'value' => $type . ':' . $target, 'label' => $label,
                    'indent' => false, 'selected' => false
                ];
            }
        }

        // If we didn't find an exact match for a selected index, use a fuzzy match:
        if (!$selectedFound && $backupSelectedIndex !== false) {
            $handlers[$backupSelectedIndex]['selected'] = true;
        }
        return $handlers;
    }
}