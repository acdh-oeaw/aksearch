<?php
/**
 * Extended authentication view helper
 * 
 * PHP version 5
 *
 * Copyright (C) AK Bibliothek Wien 2016.
 * Some functions modified by AK Bibliothek Wien, original by: UB/FU Berlin (see VuFind\ILS\Driver\Aleph)
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
 * @package  View_Helpers
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://wien.arbeiterkammer.at/service/bibliothek/
 */

namespace AkSearch\View\Helper\Root;
use Zend\View\Exception\RuntimeException;
use VuFind\View\Helper\Root\Auth as DefaultViewAuth;

class Auth extends DefaultViewAuth {

    /**
     * Render the change user data form template.
     *
     * @param array $context Context for rendering template
     *
     * @return string
     */
    public function getChangeUserDataForm($context = []) {
        return $this->renderTemplate('changeuserdata.phtml', $context);
    }
    
    
    /**
     * Render the "set password with one time password" form template.
     *
     * @param array $context Context for rendering template
     *
     * @return string
     */
    public function getSetPasswordWithOtpForm($context = []) {
    	return $this->renderTemplate('setpasswordwithotp.phtml', $context);
    }
    
    
    /**
     * Render the "request to set a new password" form template.
     *
     * @param array $context Context for rendering template
     *
     * @return string
     */
    public function getRequestSetPasswordForm($context = []) {
        return $this->renderTemplate('requestsetpassword.phtml', $context);
    }
    
    
    /**
     * Render the "set a new password" form template.
     *
     * @param array $context Context for rendering template
     *
     * @return string
     */
    public function getSetPasswordForm($context = []) {
        return $this->renderTemplate('setpassword.phtml', $context);
    }

}
