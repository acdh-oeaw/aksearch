<?php
/**
 * Piwik OptOut View Helper
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
 * @package  View Helpers
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://wien.arbeiterkammer.at/service/bibliothek/
 */

namespace AkSearch\View\Helper\Root;

class PiwikOptOut extends \Zend\View\Helper\AbstractHelper {
	
	protected $url;
	protected $optOut;
	
	
	/**
	 * Constructor for PiwikOptOut
	 * 
	 * @param string $url				URL to Piwik
	 * @param boolean $piwikOptOut		true if OptOut button should be displayed, false otherwise
	 */
	public function __construct($url, $piwikOptOut) {
		$this->url = $url;
		$this->optOut = $piwikOptOut;
	}
	
	
	/**
	 * Return Piwik OptOut code or empty string if no Piwik URL is set in config.ini or
	 * if "piwikOptOut" setting in AKsearch.ini is set to false.
	 * 
	 * @return string
	 */
	public function __invoke() {
		if (!$this->url || $this->optOut == false) {
			return '';
		}
		
		$code = $this->optOutAjax();
		$inlineScript = $this->getView()->plugin('inlinescript');
		$button = $this->optOutButton();
		$returnValue = $button.$inlineScript(\Zend\View\Helper\HeadScript::SCRIPT, $code, 'SET');

		return $returnValue;
	}
	
	
	/**
	 * Returns the html for the Piwik OptOut button.
	 * @return string	The html for the Piwik OptOut button
	 */
	private function optOutButton() {
		$optOutButton =	'<button id="akPiwikOptOutBtn"></button>&nbsp;<span id="akPiwikOptOutText"></span>';
		return $optOutButton;
	}
	
	
	/**
	 * Returns the JavaScript function that sends the Ajax requests for OptOut and OptIn. This
	 * also checks if the user opted out or not and sets some texts (e. g. from the button) accordingly.
	 * 
	 * @return string	JavaScript code for Ajax to OptOut or OptIn 
	 */
	private function optOutAjax() {
		
		return <<<EOT
$(document).ready(function() {

	// Check on load of the data privacy statement site if the user has already opted out or not
	var isTraced = null;
	$.ajax({
		method: 'POST',
		url: '{$this->url}index.php?module=API&method=AjaxOptOut.isTracked',
	}).done(function(result) {
		var xml = $(result);
		isTracked = xml.find('result').text();

		if (isTracked == '0') {
			$('#akPiwikOptOutBtn').text(vufindString['DataPrivacyStatementPiwikOptInBtnText']);
			$('#akPiwikOptOutBtn').val('doOptIn');
			$('#akPiwikOptOutText').text(vufindString['DataPrivacyStatementPiwikOptInText']);
		} else if (isTracked == '1') {
			$('#akPiwikOptOutBtn').text(vufindString['DataPrivacyStatementPiwikOptOutBtnText']);
			$('#akPiwikOptOutBtn').val('doOptOut');
			$('#akPiwikOptOutText').text(vufindString['DataPrivacyStatementPiwikOptOutText']);
		} else {
			console.log('Error: Result from Piwik is wether 0 nor 1!');
			$('#akPiwikOptOutText').html('<div style="color: red;">Could not find Piwik-Plugin "Ajax Opt Out". Please install this plugin under Piwik in "Administration -> Marketplace". Also check if the URL to your Piwik installation is correct and that Piwik runs under the same domain as AKsearch (Same-Origin-Policy). Also note that the URL should end with a slash, e. g.: https://aksearch.institution.com/piwik/</div>');
			$('#akPiwikOptOutBtn').remove();
		}
	}).fail(function(jqXHR, textStatus, errorThrown) {
		console.log(jqXHR);
		console.log(textStatus);
		console.log(errorThrown);
		$('#akPiwikOptOutBtn').remove();
		$('#akPiwikOptOutText').html('<div style="color: red;">Could not find Piwik-Plugin "Ajax Opt Out". Please install this plugin under Piwik in "Administration -> Marketplace". Also check if the URL to your Piwik installation is correct and that Piwik runs under the same domain as AKsearch (Same-Origin-Policy). Also note that the URL should end with a slash, e. g.: https://aksearch.institution.com/piwik/</div>');
	});
	
	$('#akPiwikOptOutBtn').click(function(e) {
		var btnValue = $(this).val();
		optInOrOut(btnValue);
	});

	function optInOrOut(inOrOut) {

		var urlSuffix = null;
		if (inOrOut == 'doOptOut') {
			urlSuffix = 'doIgnore';
		} else if (inOrOut == 'doOptIn') {
			urlSuffix = 'doTrack';
		}
		
		$.ajax({
			method: 'POST',
			url: '{$this->url}index.php?module=API&method=AjaxOptOut.' + urlSuffix
		}).done(function(result) {
			var xml = $(result);
			var successMessage = xml.find('success').attr('message');
			if (successMessage == 'ok') {
				if (inOrOut == 'doOptOut') {
					$('#akPiwikOptOutBtn').text(vufindString['DataPrivacyStatementPiwikOptInBtnText']);
					$('#akPiwikOptOutBtn').val('doOptIn');
					$('#akPiwikOptOutText').text(vufindString['DataPrivacyStatementPiwikOptInText']);
				} else if (inOrOut == 'doOptIn') {
					$('#akPiwikOptOutBtn').text(vufindString['DataPrivacyStatementPiwikOptOutBtnText']);
					$('#akPiwikOptOutBtn').val('doOptOut');
					$('#akPiwikOptOutText').text(vufindString['DataPrivacyStatementPiwikOptOutText']);
				}
			}
		});
	}
});

EOT;
	}
		
}
?>