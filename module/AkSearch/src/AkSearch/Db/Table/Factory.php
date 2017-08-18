<?php

namespace AkSearch\Db\Table;
use Zend\ServiceManager\ServiceManager;

class Factory extends \VuFind\Db\Table\Factory {


    /**
     * Construct the User table.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return User
     */
    public static function getUser(ServiceManager $sm) {
    	return new User (
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
    }
    
    /**
     * Construct the Loans table.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Loans
     */
    public static function getLoans() {
    	return new Loans ();
    }
    
}