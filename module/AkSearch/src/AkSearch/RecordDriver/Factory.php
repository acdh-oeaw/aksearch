<?php
/**
 * Record Driver Factory - extension for SolrMab
 *
 * PHP version 5
 *
 * Copyright (C) AK Bibliothek Wien 2015.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software Foundation,
 * Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1335, USA.
 *
 * @category AkSearch
 * @package  RecordDrivers
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://wien.arbeiterkammer.at/service/bibliothek/
 */
namespace AkSearch\RecordDriver;
use Zend\ServiceManager\ServiceManager;
use VuFind\RecordDriver\Factory as RecordDriverFactory;


class Factory extends RecordDriverFactory {
  
    /**
     * Factory for SolrMab record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SolrMab
     */
    public static function getSolrMab(ServiceManager $sm) {
    	$driver = new SolrMab (
    			$sm->getServiceLocator()->get('VuFind\Config')->get('config'),
    			null,
    			$sm->getServiceLocator()->get('VuFind\Config')->get('searches')
    	);
    	
    	$driver->attachILS (
    			$sm->getServiceLocator()->get('VuFind\ILSConnection'),
    			$sm->getServiceLocator()->get('VuFind\ILSHoldLogic'),
    			$sm->getServiceLocator()->get('VuFind\ILSTitleHoldLogic')
    	);
    	
    	return $driver;
    }
    
    
    public static function getAkfilter(ServiceManager $sm) {
    	$driver = new Akfilter(
    			$sm->getServiceLocator()->get('VuFind\Config')->get('config'),
    			null,
    			$sm->getServiceLocator()->get('VuFind\Config')->get('searches')
    	);
    	
    	$driver->attachILS (
    			$sm->getServiceLocator()->get('VuFind\ILSConnection'),
    			$sm->getServiceLocator()->get('VuFind\ILSHoldLogic'),
    			$sm->getServiceLocator()->get('VuFind\ILSTitleHoldLogic')
    	);
    	
    	return $driver;
    }
    
}