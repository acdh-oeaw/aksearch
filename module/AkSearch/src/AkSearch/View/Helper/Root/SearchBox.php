<?php

namespace AkSearch\View\Helper\Root;
use VuFind\Search\Options\PluginManager as OptionsManager;

class SearchBox extends \VuFind\View\Helper\Root\SearchBox
{

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
    protected function getCombinedHandlers($activeSearchClass, $activeHandler)
    {
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