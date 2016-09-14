<?php
/**
 * Controller for Akfilter search.
 *
 * PHP version 5
 *
 * Copyright (C) AK Bibliothek Wien 2016.
 * Modified some functions from extended original:
 * @see \VuFind\Controller\AbstractSearch
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
 * @package  Controller
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://wien.arbeiterkammer.at/service/bibliothek/
 */

namespace AkSearch\Controller;

class AkfilterController extends \VuFind\Controller\AbstractSearch {
	
    /**
     * Constructor
     */
    public function __construct() {    	
        $this->searchClassId = 'Akfilter';
        parent::__construct();
    }
    
    /**
     * Is the result scroller active?
     *
     * @return bool
     */
    protected function resultScrollerActive() {
    	$config = $this->getServiceLocator()->get('VuFind\Config')->get('Akfilter');
    	return (isset($config->Record->next_prev_navigation) && $config->Record->next_prev_navigation);
    }
    
    /**
     * Home action
     *
     * @return mixed
     */
    public function homeAction() {
    	// Set up default parameters:
    	return $this->createViewModel();
    }
    
    
    /**
     * Search action -- call standard results action
     *
     * @return mixed
     */
    public function searchAction() {
    	// Search action to call:
    	return $this->resultsAction();
    }
    

}