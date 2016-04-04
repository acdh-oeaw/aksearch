<?php
/**
 * Solr search parameters for Akfilter search.
 *
 * PHP version 5
 *
 * Copyright (C) AK Bibliothek Wien 2016.
 * Modified some functions from extended original:
 * @see \VuFind\Search\Solr\Params
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

class Params extends \VuFind\Search\Solr\Params {
	
	protected $type = null;
	
	/**
	 * Constructor 
	 * @param unknown $options
	 * @param \VuFind\Config\PluginManager $configLoader
	 */
	public function __construct($options, \VuFind\Config\PluginManager $configLoader) {
        parent::__construct($options, $configLoader);
	}
	
	// IMPORTANT:
	// These methods are important that pagination keeps the right searchHandler:
	//  - setType (see initBasicSearch())
	//  - getType
	//  - initBasicSearch (setType is used there - that's the key to right pagination!)
	
	/**
	 * Set search type setter
	 * @param String $type
	 */
	public function setType($type) {
		$this->type = $type;
	}
	
	/**
	 * Get search type
	 * 
	 * @return String search type
	 */
	public function getType() {
		return $this->type;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \VuFind\Search\Base\Params::initBasicSearch()
	 */
	protected function initBasicSearch($request) {
		// IMPORTANT for pagination - Begin
		// Set the search type from the DropDown:
		$this->setType($request->get('type'));
		// IMPORTANT for pagination - End
		
		
		// ORIGINAL - Begin
		if (is_null($lookfor = $request->get('lookfor'))) {
			return false;
		}
		// If lookfor is an array, we may be dealing with a legacy Advanced
		// Search URL.  If there's only one parameter, we can flatten it,
		// but otherwise we should treat it as an error -- no point in going
		// to great lengths for compatibility.
		if (is_array($lookfor)) {
			if (count($lookfor) > 1) {
				throw new \Exception("Unsupported search URL.");
			}
			$lookfor = $lookfor[0];
		}
	
		// Flatten type arrays for backward compatibility:
		$handler = $request->get('type');
		if (is_array($handler)) {
			$handler = $handler[0];
		}
	
		// Set the search:
		$this->setBasicSearch($lookfor, $handler);
		return true;
		// ORIGINAL - End
	}
}
?>