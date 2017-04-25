<?php
/**
 * Usergroup permission provider for AKsearch/VuFind.
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
 * @package  Authorization
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://wien.arbeiterkammer.at/service/bibliothek/
 */

namespace AkSearch\Role\PermissionProvider;
use ZfcRbac\Service\AuthorizationService;
use VuFind\ILS\Connection;
use \VuFind\Role\PermissionProvider\PermissionProviderInterface as PermissionProviderInterface;

class Usergroup implements PermissionProviderInterface {

	protected $auth;
	protected $ilsConnection;
	
	
	public function __construct(AuthorizationService $authorization, Connection $ilsConnection) {
		$this->auth = $authorization;
		$this->ilsConnection = $ilsConnection;
	}

	
	public function getPermissions($options) {
		$returnValue = null;

		if ($this->ilsConnection) {
			
			// Get user from MySQL database
			$user = $this->auth->getIdentity();
			
			// Obtain user group through ILS driver
			$profile = ($user['cat_username']) ? $this->ilsConnection->patronLogin($user['cat_username'], null) : null;
			$usergroup = ($profile != null) ? $profile['group'] : null;
			
			if (!$profile || !$usergroup || !in_array($usergroup, (array)$options)) {
				$returnValue = [];
			} else {
				$returnValue = ['loggedin'];
			}
		}
		
		// If we got this far, we can grant the permission to the loggedin role.
		return $returnValue;
	}
	
}
?>