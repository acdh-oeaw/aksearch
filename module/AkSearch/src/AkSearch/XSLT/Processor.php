<?php
/**
 * AKsearch XSLT wrapper.
 *
 * PHP version 5
 *
 * Copyright (C) AK Bibliothek Wien 2016.
 * Only overriding code from VuFind-Namespace to be able to use xsl located in custom AkSearch module.
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
 * @package  XSLT
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://wien.arbeiterkammer.at/service/bibliothek/
 */
namespace AkSearch\XSLT;
use DOMDocument, XSLTProcessor;

/**
 * VuFind XSLT wrapper
 * Original by Demian Katz <demian.katz@villanova.edu>
 * @see \VuFind\XSLT\Processor
 */
class Processor {
    /**
     * Perform an XSLT transformation and return the results.
     * Overriding original function rom VuFind-Namespace to be able to use xsl located in custom AkSearch module.
     * @see \VuFind\XSLT\Processor
     * 
     * @param string $xslt   Name of stylesheet (in application/xsl directory)
     * @param string $xml    XML to transform with stylesheet
     * @param string $params Associative array of XSLT parameters
     *
     * @return string      Transformed XML
     */
    public static function process($xslt, $xml, $params = []) {
        $style = new DOMDocument();
        $style->load(APPLICATION_PATH . '/module/AkSearch/xsl/' . $xslt);
        $xsl = new XSLTProcessor();
        $xsl->importStyleSheet($style);
        $doc = new DOMDocument();
        if ($doc->loadXML($xml)) {
            foreach ($params as $key => $value) {
                $xsl->setParameter('', $key, $value);
            }
            return $xsl->transformToXML($doc);
        }
        return '';
    }
}
