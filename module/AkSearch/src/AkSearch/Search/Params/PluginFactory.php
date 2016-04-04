<?php
/**
 * Search params plugin factory for Akfilter search.
 *
 * PHP version 5
 *
 * Copyright (C) AK Bibliothek Wien 2016.
 * Overriding some functions from extended original:
 * @see VuFind\Search\Params\PluginFactory
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
 * @package  Search_Params
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://wien.arbeiterkammer.at/service/bibliothek/
 */

namespace AkSearch\Search\Params;
use VuFind\Search\Params\PluginFactory as DefaultPluginFactory;
use Zend\ServiceManager\ServiceLocatorInterface;


class PluginFactory extends DefaultPluginFactory {
	
    /**
     * Constructor
     */
	public function __construct() {	
        $this->defaultNamespace = 'AkSearch\Search';
        $this->classSuffix = '\Params';
    }

    /**
     * Create a service for the specified name.
     *
     * @param ServiceLocatorInterface $serviceLocator Service locator
     * @param string                  $name           Name of service
     * @param string                  $requestedName  Unfiltered name of service
     *
     * @return object
     */
    public function createServiceWithName(ServiceLocatorInterface $serviceLocator,  $name, $requestedName) {
        $options = $serviceLocator->getServiceLocator()->get('VuFind\SearchOptionsPluginManager')->get($requestedName);
        $class = $this->getClassName($name, $requestedName);
        
        // Clone the options instance in case caller modifies it:
        return new $class(clone($options), $serviceLocator->getServiceLocator()->get('VuFind\Config'));
    }
}