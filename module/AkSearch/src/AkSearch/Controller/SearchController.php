<?php

namespace AkSearch\Controller;

class SearchController extends \VuFind\Controller\SearchController
{
    
	protected $akConfig;

    /**
     * Process the facets to be used as limits on the Advanced Search screen.
     *
     * @param array  $facetList    The advanced facet values
     * @param object $searchObject Saved search object (false if none)
     *
     * @return array               Sorted facets, with selected values flagged.
     */
    protected function processAdvancedFacets($facetList, $searchObject = false) {
    	
        // Process the facets
        $hierarchicalFacets = $this->getHierarchicalFacets();
        $facetHelper = null;
        if (!empty($hierarchicalFacets)) {
            $facetHelper = $this->getServiceLocator()->get('VuFind\HierarchicalFacetHelper');
        }
        
        foreach ($facetList as $facet => &$list) {
            // Hierarchical facets: format display texts and sort facets to a flat array according to the hierarchy
            if (in_array($facet, $hierarchicalFacets)) {
                $tmpList = $list['list'];
                $facetHelper->sortFacetList($tmpList, true);
                $tmpList = $facetHelper->buildFacetArray($facet, $tmpList);
                $list['list'] = $facetHelper->flattenFacetHierarchy($tmpList);
            }

            foreach ($list['list'] as $key => $value) {
                // Build the filter string for the URL:
                $fullFilter = ($value['operator'] == 'OR' ? '~' : '') . $facet . ':"' . $value['value'] . '"';

                // If we haven't already found a selected facet and the current facet has been applied to the search, we should store it as
                // the selected facet for the current control.
                if ($searchObject && $searchObject->getParams()->hasFilter($fullFilter)) {
                    $list['list'][$key]['selected'] = true;
                    // Remove the filter from the search object -- we don't want it to show up in the "applied filters" sidebar since it
                    // will already be accounted for by being selected in the filter select list!
                    $searchObject->getParams()->removeFilter($fullFilter);
                }
            }
        }
        
        // Read custom facets specified in AKsearch.ini and add them to the return array
        // This has to be processed in THEME_FOLDER/templates/search/advanced/solr.phtml
        $this->akConfig = $this->getServiceLocator()->get('\VuFind\Config')->get('AKsearch'); // Get AKsearch.ini
        $akCustomAdvFacets = $this->akConfig->CustomAdvancedFacet;
        foreach ($akCustomAdvFacets as $key => $value) {
        	$facetList[$key]['label'] = $key;
        	foreach ($value as $value1) {
        		$arrList = preg_split("/\s*,\s*/", $value1);
        		$facetList[$key]['list'][] = array('value' => $arrList[1], 'displayText' => $arrList[2], 'operator' => 'OR', 'akCustomAdvancedFacetField' => $arrList[0]);
        	}
        }

        return $facetList;
    }
    
    
    
    /**
     * New item search form
     *
     * @return mixed
     */
    public function newitemAction()
    {    	
    	// Search parameters set?  Process results.
    	if ($this->params()->fromQuery('range') !== null) {
    		return $this->forwardTo('Search', 'NewItemResults');
    	}
    	
    	return $this->createViewModel(
    			[
    					'fundList' => $this->newItems()->getFundList(),
    					'ranges' => $this->newItems()->getRanges(),
    					'newItemFilters' => $this->newItems()->getNewItemsFilter()
    			]
    	);
    }
    
    /**
     * New item result list
     *
     * @return mixed
     */
    public function newitemresultsAction()
    {
    	// Retrieve new item list:
    	$range = $this->params()->fromQuery('range');
    	$dept = $this->params()->fromQuery('department');
    	$newItemsFilter = $this->params()->fromQuery('newItemsFilter');
    	
    	// Get an array for new items filter
    	if (is_array($newItemsFilter) && !empty($newItemsFilter)) {
    		$newItemsFilterSolr = [];
    		foreach ($newItemsFilter as $label => $values) {
    			foreach ($values as $value) {
    				$arrList = preg_split("/\s*(?<!\\\):\s*/", $value);
    				if (count($arrList) == 2) {
    					$solrfield = str_replace('\\:', ':', $arrList[0]);
    					$filtervalue = str_replace('\\:', ':', $arrList[1]);
    					if (strpos($filtervalue, ':') !== false || strpos($filtervalue, ' ') !== false) {
    						$filtervalue = '"'.$filtervalue.'"'; // Phrase search for filtervalues containing colon or space.
    					}
    					$newItemsFilterSolr[] = $solrfield.':'.$filtervalue;
    				}
    			}
    		}
    		
    		// Add filters to query:
    		if (!empty($newItemsFilterSolr)) {
    			$this->getRequest()->getQuery()->set('filter', $newItemsFilterSolr);
    			//$this->getRequest()->getQuery()->set('hiddenFilters', $newItemsFilterSolr);
    		}
    	}
    	
    	
    	// Check if sorting of new items should be changed
    	$this->akConfig = $this->getServiceLocator()->get('\VuFind\Config')->get('AKsearch'); // Get AKsearch.ini
    	$akUseRelevance = $this->akConfig->NewItemsSort->relevance;
    	$akDefaultSort = $this->akConfig->NewItemsSort->default;
    	if (!$akUseRelevance) {
    		$this->getRequest()->getQuery()->set('relevance', 'false');
    		if (!isset($this->getRequest()->getQuery()->sort)) {
    			$this->getRequest()->getQuery()->set('sort', $this->akConfig->NewItemsSort->default);
    		}
    	}
    	    	
    	// Validate the range parameter -- it should not exceed the greatest
    	// configured value:
    	$maxAge = $this->newItems()->getMaxAge();
    	if ($maxAge > 0 && $range > $maxAge) {
    		$range = $maxAge;
    	}
    
    	// Are there "new item" filter queries specified in the config file?
    	// If so, load them now; we may add more values. These will be applied
    	// later after the whole list is collected.
    	$hiddenFilters = $this->newItems()->getHiddenFilters();
    
    	// Depending on whether we're in ILS or Solr mode, we need to do some
    	// different processing here to retrieve the correct items:
    	if ($this->newItems()->getMethod() == 'ils') {
    		// Use standard search action with override parameter to show results:
    		$bibIDs = $this->newItems()->getBibIDsFromCatalog(
    				$this->getILS(),
    				$this->getResultsManager()->get('Solr')->getParams(),
    				$range, $dept, $this->flashMessenger()
    				);
    		$this->getRequest()->getQuery()->set('overrideIds', $bibIDs);
    	} else {
    		// Use a Solr filter to show results:
    		$hiddenFilters[] = $this->newItems()->getSolrFilter($range);
    	}
    
    	// If we found hidden filters above, apply them now:
    	if (!empty($hiddenFilters)) {
    		$this->getRequest()->getQuery()->set('hiddenFilters', $hiddenFilters);
    	}
    
    	// Don't save to history -- history page doesn't handle correctly:
    	$this->saveToHistory = false;
    
    	// Call rather than forward, so we can use custom template
    	$view = $this->resultsAction();
    
    	// Customize the URL helper to make sure it builds proper new item URLs
    	// (check it's set first -- RSS feed will return a response model rather
    	// than a view model):
    	if (isset($view->results)) {
    		$url = $view->results->getUrlQuery();
    		$url->setDefaultParameter('range', $range);
    		$url->setDefaultParameter('department', $dept);
    		$url->setDefaultParameter('relevance', $akUseRelevance);
    		$url->setSuppressQuery(true);
    	}
    
    	
    	return $view;
    }
    
}