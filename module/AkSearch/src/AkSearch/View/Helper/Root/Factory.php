<?php
/**
 * Extended factory for root view helpers for Akfilter search.
 *
 * PHP version 5
 *
 * Copyright (C) AK Bibliothek Wien 2017.
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
 * @package  View_Helper_Root
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://wien.arbeiterkammer.at/service/bibliothek/
 */

namespace AkSearch\View\Helper\Root;
use Zend\ServiceManager\ServiceManager;

class Factory extends \VuFind\View\Helper\Root\Factory {

	
    /**
     * Construct the Auth helper.
     *
     * @param ServiceManager $sm Service manager.
     * @return Auth
     */
    public static function getAuth(ServiceManager $sm) {
    	return new \AkSearch\View\Helper\Root\Auth($sm->getServiceLocator()->get('VuFind\AuthManager'));
    }
    
    
    /**
     * Construct the Citation helper.
     *
     * @param ServiceManager $sm Service manager.
     * @return Citation
     */
    public static function getCitation(ServiceManager $sm) {
    	return new \AkSearch\View\Helper\Root\Citation($sm->getServiceLocator()->get('VuFind\DateConverter'));
    }
    
    
    /**
     * Construct the Piwik OptOut helper.
     * 
     * @param ServiceManager $sm
     * @return \AkSearch\View\Helper\Root\PiwikOptOut
     */
    public static function getPiwikOptOut(ServiceManager $sm) {
    	$config = $sm->getServiceLocator()->get('VuFind\Config');
    	$piwikUrl = $config->get('config')->Piwik->url; // Piwik URL from config.ini
    	$piwikOptOut = $config->get('AKsearch')->DataPrivacyStatement->piwikOptOut;

    	return new \AkSearch\View\Helper\Root\PiwikOptOut($piwikUrl, $piwikOptOut);
    }
    
    
    /**
     * Construct the SearchBox helper.
     * Updated by AK Bibliothek Wien: returning \AkSearch\View\Helper\Root\SearchBox
     * for making Akfilter search possible.
     *
     * @param ServiceManager $sm Service manager.
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