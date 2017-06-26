<?php

namespace AkSearch\Controller\Plugin;
use DateTime;
use Zend\Config\Config;


class NewItems extends \VuFind\Controller\Plugin\NewItems {
    /**
     * [NewItem] configuration from searches.ini
     *
     * @var Config
     */
    protected $config;
    
    /**
     * [Catalog] configuration from config.ini
     *
     * @var Config
     */
    protected $catalogConfig;
    
    /**
     * AKsearch new items filter configuration
     *
     * @var Config
     */
    protected $akNewItemsConfig;

    /**
     * Constructor
     *
     * @param Config $config Configuration
     */
    public function __construct(Config $config, Config $catalogConfig, Config $akNewItemsConfig) {
        $this->config = $config;
        $this->catalogConfig = $catalogConfig;
        $this->akNewItemsConfig = $akNewItemsConfig;
    }

    
    /**
     * Get range settings
     *
     * @return array
     */
    public function getRanges()  {
        // Find out if there are user configured range options; if not,
        // default to the standard 1/5/30 days:
        $ranges = [];

        if (isset($this->config->ranges)) {
            $tmp = explode(',', $this->config->ranges);
            foreach ($tmp as $range) {
            	if ($range !== 'datepicker') {
                	$range = intval($range);
            	}
                if ($range > 0 || $range === 'datepicker') {
                    $ranges[] = $range;
                }
            }
        }
        if (empty($ranges)) {
            $ranges = [1, 5, 30];
        }
        return $ranges;
    }
    
    
    /**
     * Get new items filter settings (see section [NewItemsFilter] in AKsearch.ini)
     * 
     * @return array
     */
    public function getNewItemsFilter() {
    	$newItemsFilter = [];
    	$newItemsFilterConfig = $this->akNewItemsConfig;

    	if (isset($newItemsFilterConfig)) {
    		foreach ($newItemsFilterConfig as $label => $values) {
    			$newItemsFilter[$label]['label'] = $label;
    			foreach ($values as $value) {
    				// Split string at comma, except the comma is escaped by backslash:
    				$arrList = preg_split("/\s*(?<!\\\),\s*/", $value);
    				
    				if (count($arrList) == 3) {
    					// Replace possibly escaped commas with normal comma:
    					$solrfield = str_replace('\\,', ',', $arrList[0]);
    					$filtervalue = str_replace('\\,', ',', $arrList[1]);
    					$filterlabel = str_replace('\\,', ',', $arrList[2]);
    					$newItemsFilter[$label]['values'][] = array('solrfield' => $solrfield, 'filtervalue' => $filtervalue, 'filterlabel' => $filterlabel);
    				}
    			}
    		}
    	}
    	return $newItemsFilter;
    }
        
    
    /**
     * Get a Solr filter to limit to the specified number of days - extended version for use with Alma.
     *
     * @param int $range Days to search
     *
     * @return string
     */
    public function getSolrFilter($range) {
    	$datePicker = false;
    	
    	// Default - with no. of days to calculate
    	$returnValue = 'first_indexed:[NOW-' . $range . 'DAY TO NOW]';
    	
    	// Special AKsearch date picker function. We have to use a FROM - TO query here
    	if (substr($range, 0, 10) == 'datePicker') {
    		$datePicker = true;
    		$dateBeginStr = substr($range, 11, 19);
    		$dtFirstDate = DateTime::createFromFormat('Ymd', $dateBeginStr);
    		$from = $dtFirstDate->format('Y-m-d\T00:00:00\Z');
    		$to = $dtFirstDate->format('Y-m-t\T23:59:59\Z');
    		
    	}
    	
    	if ($datePicker) { // If we use date picker
    		if (isset($this->catalogConfig->driver) && $this->catalogConfig->driver == 'Alma') { // If we use Alma
    			$returnValue = 'receivingDates_date_mv:['.$from.' TO '.$to.']';
    		} else {
    			$returnValue = 'first_indexed:['.$from.' TO '.$to.']';
    		}
    	} else {
    		if (isset($this->catalogConfig->driver) && $this->catalogConfig->driver == 'Alma') { // If we use Alma
    			$returnValue = 'receivingDates_date_mv:[NOW-' . $range . 'DAY TO NOW]';
    		}
    	}
    	
    	return $returnValue;
    }
    
}