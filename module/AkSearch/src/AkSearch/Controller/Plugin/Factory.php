<?php

namespace AkSearch\Controller\Plugin;
use Zend\ServiceManager\ServiceManager;

class Factory {

    /**
     * Construct the NewItems plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Reserves
     */
    public static function getNewItems(ServiceManager $sm) {
    	// [NewItem] from searches.ini
        $searchesIni = $sm->getServiceLocator()->get('VuFind\Config')->get('searches');
        $newItemConfig = isset($searchesIni->NewItem) ? $searchesIni->NewItem : new \Zend\Config\Config([]);
        
        // [Catalog] from config.ini
        $configIni = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        $catalogConfig = isset($configIni->Catalog) ? $configIni->Catalog : new \Zend\Config\Config([]);
        
        // [NewItemsFilter] of AKsearch.ini
        $akSearchIni = $sm->getServiceLocator()->get('VuFind\Config')->get('AKsearch'); // Get AKsearch.ini
        $akNewItemsConfig = isset($akSearchIni->NewItemsFilter) ? $akSearchIni->NewItemsFilter : new \Zend\Config\Config([]);
        
        return new NewItems($newItemConfig, $catalogConfig, $akNewItemsConfig);
    }
    
    
    /**
     * Construct the AkSearch plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Reserves
     */
    public static function getAkSearch(ServiceManager $sm) {
    	// Get config.ini
    	$config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
    	
    	// Get Alma.ini
    	$configAlma = $sm->getServiceLocator()->get('VuFind\Config')->get('Alma');

    	// Get AKsearch.ini
    	//$configAksearch = $sm->getServiceLocator()->get('VuFind\Config')->get('AKsearch');
    	
    	// Get DB table manager
    	$dbTableManager = $sm->getServiceLocator()->get('VuFind\DbTablePluginManager');
    	
    	// Get HTTP service
    	$httpService = $sm->getServiceLocator()->get('VuFind\Http');
    	
    	return new AkSearch($config, $configAlma, $dbTableManager, $httpService);
    }
}