<?php

/**
 * Extended Ajax Controller Module
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
 * @category AKsearch
 * @package  Controller
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://wien.arbeiterkammer.at/service/bibliothek/
 */
namespace AkSearch\Controller;

class AkAjaxController extends \VuFind\Controller\AjaxController implements \VuFindHttp\HttpServiceAwareInterface {

	use \VuFindHttp\HttpServiceAwareTrait;
	
	/**
	 * Call entity facts API
	 * Example: http://hub.culturegraph.org/entityfacts/118540238
	 * 
	 * @return JSON
	 */
	public function getEntityFactAjax() {
		$this->outputMode = 'json';
		$gndid = $this->params()->fromQuery('gndid');
		$client = $this->getServiceLocator()->get('VuFind\Http')->createClient('http://hub.culturegraph.org/entityfacts/' . $gndid);
		$client->setMethod('GET');
		$result = $client->send();
		
		// If an error occurs
		if (!$result->isSuccess()) {
			return $this->output($this->translate('An error has occurred'), self::STATUS_ERROR);
		}
				
		$json = $result->getBody();
		return $this->output($json, self::STATUS_OK);		

	}
}
