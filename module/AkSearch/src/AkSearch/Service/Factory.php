<?php

namespace AkSearch\Service;
use Zend\ServiceManager\ServiceManager;
use VuFind\Service\Factory as DefaultFactory;

class Factory extends DefaultFactory {
    

    /**
     * Construct the ILS hold logic for AKsearch.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \AkSearch\ILS\Logic\Holds
     */
    public static function getILSHoldLogic(ServiceManager $sm) {
        return new \AkSearch\ILS\Logic\Holds(
            $sm->get('VuFind\ILSAuthenticator'), $sm->get('VuFind\ILSConnection'),
            $sm->get('VuFind\HMAC'), $sm->get('VuFind\Config')->get('config')
        );
    }

}