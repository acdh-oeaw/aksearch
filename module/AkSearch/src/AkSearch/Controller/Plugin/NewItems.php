<?php

namespace AkSearch\Controller\Plugin;
use Zend\Config\Config;

//namespace VuFind\Controller\Plugin;
//use Zend\Mvc\Controller\Plugin\AbstractPlugin;


class NewItems extends \VuFind\Controller\Plugin\NewItems {
    /**
     * Configuration
     *
     * @var Config
     */
    protected $config;
    
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
    public function __construct(Config $config, Config $akNewItemsConfig) {
        $this->config = $config;
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
    
}