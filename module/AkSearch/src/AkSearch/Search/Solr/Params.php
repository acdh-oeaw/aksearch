<?php

namespace AkSearch\Search\Solr;
//use VuFindSearch\ParamBag;


class Params extends \VuFind\Search\Solr\Params {
	/**
	 * Get a user-friendly string to describe the provided facet field.
	 *
	 * @param string $field Facet field name.
	 *
	 * @return string       Human-readable description of field.
	 */
	public function getFacetLabel($field) {
		return isset($this->facetConfig[$field]) ? $this->facetConfig[$field] : 'unrecognized_facet_label';
	}
	
	/**
	 * Return an array structure containing information about all current filters.
	 *
	 * @param bool $excludeCheckboxFilters Should we exclude checkbox filters from
	 * the list (to be used as a complement to getCheckboxFacets()).
	 *
	 * @return array                       Field, values and translation status
	 */
	public function getFilterList($excludeCheckboxFilters = false)
	{
		// Get a list of checkbox filters to skip if necessary:
		$skipList = $excludeCheckboxFilters
		? $this->getCheckboxFacetValues() : [];
	
		$list = [];
		$translatedFacets = $this->getOptions()->getTranslatedFacets();
		// Loop through all the current filter fields
		foreach ($this->filterList as $field => $values) {
			list($operator, $field) = $this->parseOperatorAndFieldName($field);
			$translate = in_array($field, $translatedFacets);
			// and each value currently used for that field
			foreach ($values as $value) {
				// Add to the list unless it's in the list of fields to skip:
				if (!isset($skipList[$field]) || !in_array($value, $skipList[$field])) {
					$facetLabel = $this->getFacetLabel($field);
					if ($facetLabel == 'unrecognized_facet_label') {
						//$facetLabel = $this->transEsc($field);
						$facetLabel = $this->translate($field);
						//$translator = $this->getServiceLocator()->get('VuFind\Translator')->getTranslator();
					}
					
					$list[$facetLabel][] = $this->formatFilterListEntry($field, $value, $operator, $translate);
				}
			}
		}
		return $list;
	}
}
