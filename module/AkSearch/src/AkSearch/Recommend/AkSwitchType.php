<?php
/**
 * Extension for SwitchType recommendation
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
 * @package  Recommendations
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://wien.arbeiterkammer.at/service/bibliothek/
 */

namespace AkSearch\Recommend;


class AkSwitchType extends \VuFind\Recommend\SwitchType {

	/**
	 * Old search route that should be replaced by new search route
	 *
	 * @var string
	 */
	protected $oldRoute;
	
	/**
	 * New search route that replaces old search route
	 * 
	 * @var string
	 */
	protected $newRoute;
	

    /**
     * Store the configuration of the recommendation module.
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings) {
        $params = explode(':', $settings);
        $this->oldRoute = !empty($params[0]) ? $params[0] : null;
        $this->newRoute = !empty($params[1]) ? $params[1] : 'Solr';
        $this->newHandler = !empty($params[2]) ? $params[2] : 'AllFields';
        $this->newHandlerName = isset($params[3]) ? $params[3] : 'All Fields';

    }
    
    /**
     * Get old route
     * 
     * @return string
     */
    public function getOldRoute() {
    	return $this->oldRoute;
    }
    
    /**
     * Get new route
     *
     * @return string
     */
    public function getNewRoute() {
    	return $this->newRoute;
    }
}