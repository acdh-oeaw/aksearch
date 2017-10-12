<?php

namespace AkSearch\Backend\Solr;

use VuFindSearch\Query\Query;
use VuFindSearch\ParamBag;


class Connector extends \VuFindSearch\Backend\Solr\Connector {


    /**
     * Return document specified by id.
     *
     * @param string   $id     The document to retrieve from Solr
     * @param ParamBag $params Parameters
     *
     * @return string
     */
    public function retrieve($id, ParamBag $params = null) {
    	
    	// Get possible ID fields that are defined in AKsearch.ini, split them to an array and construct a solr query string
    	// that ORs together the fields. Then, we use it in the search query.
    	$idFieldsArr = preg_split('/\s*,\s*/', $this->uniqueKey);
    	$solrQueryStrings = [];
    	foreach ($idFieldsArr as $uKey) {
    		$solrQueryStrings[] = sprintf('%s:"%s"', $uKey, addcslashes($id, '"'));
    	}
    	$solrQueryString = implode(' || ', $solrQueryStrings);
    	
        $params = $params ?: new ParamBag();
        $params->set('q', $solrQueryString);

        $handler = $this->map->getHandler(__FUNCTION__);
        $this->map->prepare(__FUNCTION__, $params);

        return $this->query($handler, $params);
    }

}
