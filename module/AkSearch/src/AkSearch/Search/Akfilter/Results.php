<?php
namespace AkSearch\Search\Akfilter;

class Results extends \VuFind\Search\Solr\Results
//class Results extends \VuFind\Search\Base\Results
{

	
    /**
     * Search backend identifiers.
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