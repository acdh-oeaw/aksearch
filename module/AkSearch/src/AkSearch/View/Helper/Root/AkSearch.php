<?php
/**
 * General View Helper for AKsearch
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
 * @package  View Helpers
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://wien.arbeiterkammer.at/service/bibliothek/
 */

namespace AkSearch\View\Helper\Root;

class AkSearch extends \Zend\View\Helper\AbstractHelper {
	
	/**
	 * Config loader for loading configurations. It is passed to the constructor
	 * by \AkSearch\View\Helper\Root\Factory::getAkSearch()
	 * 
	 * @var \Zend\Config\Config
	 */
	protected $configLoader;
	
	
	
	/**
	 * Constructor for general AkSearch view helper
	 * @param \Zend\Config\Config $configLoader
	 */
	public function __construct($configLoader) {
		$this->configLoader = $configLoader;
	}
	
	
	/**
	 * Get a config in a phtml site. Usage example:
	 * $this->akSearch()->getConfig('AKsearch')->User
	 * 
	 * @param string $configFileName
	 * @return mixed
	 */
	public function getConfig($configFileName = null) {
		if ($configFileName == null || empty($configFileName)) {
			$configFileName = 'config'; // Default
		}
		return $this->configLoader->get($configFileName);
	}
	
}
?>