<?php
/**
 * Solr search results for Akfilter search.
 *
 * PHP version 5
 *
 * Copyright (C) AK Bibliothek Wien 2016.
 * Modified some functions from extended original:
 * @see \VuFind\Search\Solr\Results
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

class Results extends \VuFind\Search\Solr\Results {

	
    /**
     * Search backend identifiers.
     * Setting appropriate value for Akfilter search.
     *
     * @var string
     */
    protected $backendId = 'Akfilter';

    /**
     * Support method for performAndProcessSearch -- perform a search based on the
     * parameters passed to the object.
     *
     * @return void
     */
    protected function performSearch()
    {    	
    	$query  = $this->getParams()->getQuery();
    	$limit  = $this->getParams()->getLimit();
    	$offset = $this->getStartRecord() - 1;
    	$params = $this->getParams()->getBackendParameters();
    	$searchService = $this->getSearchService();
    	
    	// IMPORTANT - for pagination. Set active search handler:
    	$handlerType = $this->getParams()->getType();
    	$query->setHandler($handlerType);
    
    	try {
    		$collection = $searchService->search($this->backendId, $query, $offset, $limit, $params);
    	} catch (\VuFindSearch\Backend\Exception\BackendException $e) {
    		// If the query caused a parser error, see if we can clean it up:
    		if ($e->hasTag('VuFind\Search\ParserError') && $newQuery = $this->fixBadQuery($query)) {
    			// We need to get a fresh set of $params, since the previous one was
    			// manipulated by the previous search() call.
    			$params = $this->getParams()->getBackendParameters();
    			$collection = $searchService->search($this->backendId, $newQuery, $offset, $limit, $params);
    		} else {
    			throw $e;
    		}
    	}
    
    	$this->responseFacets = $collection->getFacets();
    	$this->resultTotal = $collection->getTotal();
    	    
    	// Process spelling suggestions
    	$spellcheck = $collection->getSpellcheck();
    	$this->spellingQuery = $spellcheck->getQuery();
    	$this->suggestions = $this->getSpellingProcessor()->getSuggestions($spellcheck, $this->getParams()->getQuery());
    
    	// Construct record drivers for all the items in the response:
    	$this->results = $collection->getRecords();
    }
    
    /**
     * Returns the stored list of facets for the last search
     *
     * @param array $filter Array of field => on-screen description listing
     * all of the desired facet fields; set to null to get all configured values.
     *
     * @return array        Facets data arrays
     */
    public function getFacetList($filter = null)
    {
        // Make sure we have processed the search before proceeding:
        if (null === $this->responseFacets) {
            $this->performAndProcessSearch();
        }

        // If there is no filter, we'll use all facets as the filter:
        if (is_null($filter)) {
            $filter = $this->getParams()->getFacetConfig();
        }

        // Start building the facet list:
        $list = [];

        // Loop through every field returned by the result set
        $fieldFacets = $this->responseFacets->getFieldFacets();
        $translatedFacets = $this->getOptions()->getTranslatedFacets();
        
        foreach (array_keys($filter) as $field) {
            $data = isset($fieldFacets[$field]) ? $fieldFacets[$field] : [];
            // Skip empty arrays:
            if (count($data) < 1) {
                continue;
            }
            // Initialize the settings for the current field
            $list[$field] = [];
            // Add the on-screen label
            $list[$field]['label'] = $filter[$field];
            // Build our array of values for this field
            $list[$field]['list']  = [];
            // Should we translate values for the current facet?
            if ($translate = in_array($field, $translatedFacets)) {
                $translateTextDomain = $this->getOptions()->getTextDomainForTranslatedFacet($field);
            }
            // Loop through values:
            foreach ($data as $value => $count) {
                // Initialize the array of data about the current facet:
                $currentSettings = [];
                $currentSettings['value'] = $value;
                $currentSettings['displayText']  = $translate ? $this->translate("$translateTextDomain::$value") : $value;
                $currentSettings['count'] = $count;
                $currentSettings['operator'] = $this->getParams()->getFacetOperator($field);
                $currentSettings['isApplied'] = $this->getParams()->hasFilter("$field:" . $value) || $this->getParams()->hasFilter("~$field:" . $value);

                // Store the collected values:
                $list[$field]['list'][] = $currentSettings;
            }
        }
        return $list;
    }  
}
?>