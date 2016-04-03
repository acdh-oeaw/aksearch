<?php

namespace AkSearch\View\Helper\Root;
use Zend\ServiceManager\ServiceManager;

class Factory extends \VuFind\View\Helper\Root\Factory {

    /**
     * Construct the SearchBox helper.
     * Updated by AK Bibliothek Wien: returning \AkSearch\View\Helper\Root\SearchBox
     * for making Akfilter search possible.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SearchBox
     */
    public static function getSearchBox(ServiceManager $sm) {
        $config = $sm->getServiceLocator()->get('VuFind\Config');
        return new \AkSearch\View\Helper\Root\SearchBox(
            $sm->getServiceLocator()->get('VuFind\SearchOptionsPluginManager'),
            $config->get('searchbox')->toArray()
        );
    }
}
