<?php

namespace AkSearch\Recommend;
use Zend\ServiceManager\ServiceManager;

class Factory extends \VuFind\Recommend\Factory {

    public static function getSideFacets(ServiceManager $sm) {
        return new SideFacets(
            $sm->getServiceLocator()->get('VuFind\Config'),
            $sm->getServiceLocator()->get('VuFind\HierarchicalFacetHelper')
        );
    }
}