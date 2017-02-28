<?php

namespace AkSearch\Controller;

class BrowseController extends \VuFind\Controller\BrowseController {

   
    /**
     * Get a list of letters to display in alphabetical mode.
     *
     * @return array
     */
    protected function getAlphabetList() {
    	
    	$akConfig = $this->getServiceLocator()->get('\VuFind\Config')->get('AKsearch'); // Get AKsearch.ini
    	$akBrowseNoNumbersObj = $akConfig->BrowseCatalogueNoNumbers->browseNoNumbers; // Get browseNoNumbers setting from AKsearch.ini
    	$akBrowseNoNumbersArr = $akBrowseNoNumbersObj->toArray(); // Convert config object to array
    	
        // Get base alphabet:
        $chars = isset($this->config->Browse->alphabet_letters) ? $this->config->Browse->alphabet_letters : 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        if (!in_array($this->getCurrentAction(), $akBrowseNoNumbersArr)) {
        	// Put numbers in the front for Era since years are important:
        	if ($this->getCurrentAction() == 'Era') {
        		$chars = '0123456789' . $chars;
        	} else {
        		$chars .= '0123456789';
        	}
        }
        
        // ALPHABET TO ['value','displayText']
        // (value has asterix appended for Solr, but is unmodified for tags)
        $suffix = $this->getCurrentAction() == 'Tag' ? '' : '*';
        $callback = function ($letter) use ($suffix) {
            return ['value' => $letter . $suffix, 'displayText' => $letter];
        };
        preg_match_all('/(.)/u', $chars, $matches);
        return array_map($callback, $matches[1]);
    }
}