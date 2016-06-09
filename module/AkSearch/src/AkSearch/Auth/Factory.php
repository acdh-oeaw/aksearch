<?php
/**
 * Extended factory for authentication services.
 *
 * PHP version 5
 *
 * Copyright (C) AK Bibliothek Wien 2016.
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
 * @category AKsearch
 * @package  Authentication
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://wien.arbeiterkammer.at/service/bibliothek/
 */

namespace AkSearch\Auth;
use Zend\ServiceManager\ServiceManager;
use VuFind\Auth\Factory as DefaultAuthFactory;

/**
 * Extending default factory for authentication services.
 * 
 * @codeCoverageIgnore
 */
class Factory extends DefaultAuthFactory {

	
	/**
	 * Overriding the construction of the ILS plugin because we need to return AkSearch\Auth\ILS instead of VuFind\Auth\ILS
	 *
	 * @param ServiceManager $sm Service manager.
	 *
	 * @return ILS
	 */
	public static function getILS(ServiceManager $sm) {
		return new ILS(
				$sm->getServiceLocator()->get('VuFind\ILSConnection'),
				$sm->getServiceLocator()->get('VuFind\ILSAuthenticator')
				);
	}
	
	
    /**
     * Overriding the default VuFind authentication manager.
     * This returns an extended Auth manager that conains a function called "supportsUserDataChange". This function
     * is used to check if we should show a "Change user data" page in the user account. We also get the AKsearch.ini
     * configuration here and set it to the extended Auth manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Manager
     */
    public static function getManager(ServiceManager $sm)
    {
        // Set up configuration:
        $config = $sm->get('VuFind\Config')->get('config');
        $akConfig = $sm->get('VuFind\Config')->get('AKsearch');
        
        try {
            // Check if the catalog wants to hide the login link, and override
            // the configuration if necessary.
            $catalog = $sm->get('VuFind\ILSConnection');
            if ($catalog->loginIsHidden()) {
                $config = new \Zend\Config\Config($config->toArray(), true);
                $config->Authentication->hideLogin = true;
                $config->setReadOnly();
            }
        } catch (\Exception $e) {
            // Ignore exceptions; if the catalog is broken, throwing an exception
            // here may interfere with UI rendering. If we ignore it now, it will
            // still get handled appropriately later in processing.
            error_log($e->getMessage());
        }

        // Load remaining dependencies:
        $userTable = $sm->get('VuFind\DbTablePluginManager')->get('user');
        $sessionManager = $sm->get('VuFind\SessionManager');
        $pm = $sm->get('VuFind\AuthPluginManager');
        $cookies = $sm->get('VuFind\CookieManager');

        // Build the object:
        return new Manager($config, $userTable, $sessionManager, $pm, $cookies, $akConfig);
    }

}