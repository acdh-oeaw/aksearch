<?php

namespace AkSearch\Controller;
use Zend\ServiceManager\ServiceManager;

class Factory extends \VuFind\Controller\Factory {

    public static function getBrowseController(ServiceManager $sm) {
        return new BrowseController($sm->getServiceLocator()->get('VuFind\Config')->get('config'));
    }
}