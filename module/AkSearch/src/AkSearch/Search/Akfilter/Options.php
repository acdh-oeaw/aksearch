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

class Options extends \VuFind\Search\Solr\Options {
	
	/**
	 * Constructor
	 *
	 * @param \VuFind\Config\PluginManager $configLoader Config loader
	 */
	public function __construct(\VuFind\Config\PluginManager $configLoader) {
		parent::__construct($configLoader);
		$akfilterSettings = $configLoader->get('Akfilter');
		
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
			$this->basicHandlers[$akfilterKey.':'.$akfilterValues->toptarget[0]] = $akfilterValues->toplabel[0];			
			if (isset($akfilterValues->subtarget)) {
				foreach ($akfilterValues->subtarget as $subtargetKey => $subtargetValue) {
					$this->basicHandlers[$akfilterKey.':'.$subtargetValue] = $akfilterValues->sublabel[$subtargetKey];
				}
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
		return false;
	}	
}
?>