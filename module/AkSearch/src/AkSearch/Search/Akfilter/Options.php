<?php
/**
 * Solr search options for Akfilter search.
 *
 * PHP version 5
 *
 * Copyright (C) AK Bibliothek Wien 2016.
 * Modified some functions from extended original:
 * @see \VuFind\Search\Solr\Options
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
 * @package  Search_Akfilter
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://wien.arbeiterkammer.at/service/bibliothek/
 */

namespace AkSearch\Search\Akfilter;


use \ZfcRbac\Service\AuthorizationServiceAwareInterface,
\ZfcRbac\Service\AuthorizationServiceAwareTrait;


class Options extends \VuFind\Search\Solr\Options implements AuthorizationServiceAwareInterface {
	
	use AuthorizationServiceAwareTrait;
	
	
	/**
	 * Constructor
	 *
	 * @param \VuFind\Config\PluginManager $configLoader Config loader
	 */
	public function __construct(\VuFind\Config\PluginManager $configLoader) {
		parent::__construct($configLoader);
		$akfilterSettings = $configLoader->get('Akfilter');
				
		// Initialize the authorization service (for permissions)
		$init = new \ZfcRbac\Initializer\AuthorizationServiceInitializer();
		$init->initialize($this, $configLoader);
		$auth = $this->getAuthorizationService();
		if (!$auth) {
			throw new \Exception('Authorization service missing');
		}
	
		// TODO: Check if this is really necessary
		// Unset default handler
		unset($this->defaultHandler);
		
		// TODO: Is this really necessary?
		$this->defaultHandler = 'AkfilterAll';
				
		// First unset from superior options which are set with parent::__construct($configLoader)
		unset($this->basicHandlers);
		
		// Iterate over the Akfilter settings and set the handlers for the searchbox:
		//   Filter values (basicHandlers[key:VALUE]) are prepended with filter key (basicHandlers[KEY:value])
		//   defined in Akfilter.ini and separated from it by colon (:). Filter values after the colon must be
		//   defined as search options in searchspecs.yaml
		foreach ($akfilterSettings as $akfilterKey => $akfilterValues) {
			if (isset($akfilterValues->toppermission[0])) {
				$topPermissionIsGranted = $auth->isGranted($akfilterValues->toppermission[0]);
				if ($topPermissionIsGranted) {
					$this->basicHandlers[$akfilterKey.':'.$akfilterValues->toptarget[0]] = $akfilterValues->toplabel[0];
					$subTargets = (isset($akfilterValues->subtarget)) ? $akfilterValues->subtarget : null;
					if ($subTargets != null && !empty($subTargets)) {
						$this->setSubtargets($auth, $akfilterKey, $akfilterValues);
					}
				}
			} else {
				$this->basicHandlers[$akfilterKey.':'.$akfilterValues->toptarget[0]] = $akfilterValues->toplabel[0];
				$subTargets = (isset($akfilterValues->subtarget)) ? $akfilterValues->subtarget : null;
				if ($subTargets != null && !empty($subTargets)) {
					$this->setSubtargets($auth, $akfilterKey, $akfilterValues);
				}
			}
		}
	}
	
	
	/**
	 * Set the sub search targets, depending on the permission in permissions.ini
	 * 
	 * @param unknown $auth				The authentication object that handles the permissions of permissions.ini
	 * @param unknown $akfilterKey		The key from the top target array that contains the sub target(s)
	 * @param unknown $akfilterData		The data from the Akfilter.ini file
	 */
	private function setSubtargets($auth, $akfilterKey, $akfilterData) {
		foreach ($akfilterData->subtarget as $subtargetKey => $subtargetValue) {
			if (isset($akfilterData->subpermission[$subtargetKey])) {
				$subPermissionIsGranted = $auth->isGranted($akfilterData->subpermission[$subtargetKey]);
				if ($subPermissionIsGranted) {
					$this->basicHandlers[$akfilterKey.':'.$subtargetValue] = $akfilterData->sublabel[$subtargetKey];
				}
			} else {
				$this->basicHandlers[$akfilterKey.':'.$subtargetValue] = $akfilterData->sublabel[$subtargetKey];
			}
		}
	}
	
	
	/**
	 * Return the route name for the search results action.
	 *
	 * @return string
	 */
	public function getSearchAction() {
		return 'akfilter-results';
	}
	
	
	/**
	 * Return the route name of the action used for performing advanced searches.
	 * Returns false if the feature is not supported.
	 *
	 * @return string|bool
	 */
	public function getAdvancedSearchAction() {
		//return 'akfilter-advanced';
		//return false;
		return 'search-advanced';
	}	
}
?>