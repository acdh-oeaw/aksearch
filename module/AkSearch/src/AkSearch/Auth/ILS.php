<?php
/**
 * Extended ILS authentication module
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
use VuFind\Auth\ILS as DefaultAuthILS;

class ILS extends DefaultAuthILS {
   

    /**
     * Does this authentication method support user data changing?
     *
     * @return bool
     */
    public function supportsUserDataChange() {
    	// Check if the function exists in ILS Driver (e. g. Aleph)
    	$supportsUserDataChange = false !== $this->getCatalog()->checkCapability('changeUserData');
    	return $supportsUserDataChange;
    }

}