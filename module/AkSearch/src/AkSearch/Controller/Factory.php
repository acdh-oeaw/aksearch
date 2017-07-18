<?php

namespace AkSearch\Controller;
use Zend\ServiceManager\ServiceManager;

class Factory extends \VuFind\Controller\Factory {

	
    public static function getBrowseController(ServiceManager $sm) {
        return new BrowseController($sm->getServiceLocator()->get('VuFind\Config')->get('config'));
    }
    
    
    public static function getApiController(ServiceManager $sm) {
    	return new ApiController(
    			$sm->getServiceLocator()->get('VuFind\Config'),
    			$sm->getServiceLocator()->get('VuFind\DbTablePluginManager'),
    			$sm->getServiceLocator()->get('VuFind\Http')
    	);
    }
    
    
    public static function getAkSitesController(ServiceManager $sm) {
    	// To inject the AkSearch Controller Plugin, we could set: $sm->getServiceLocator()->get('ControllerPluginManager')->get('AkSearch')
    	return new AkSitesController();
    }

}