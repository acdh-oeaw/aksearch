<?php

namespace AkSearch\Search\Factory;

use VuFindSearch\Backend\Solr\Connector;
use VuFindSearch\Backend\Solr\Backend;


class SolrDefaultBackendFactory extends \VuFind\Search\Factory\SolrDefaultBackendFactory {

    
    /**
     * Create the SOLR connector.
     * Updated for use with other ID fields (defined in AKsearch.ini) of Solr index.
     *
     * @return Connector
     */
    protected function createConnector() {

    	$config = $this->config->get('config');
    	
    	// Get AK config and check if ID fields are set. If yes, we will query all the given fields for record IDs.
    	$akConfig = $this->config->get('AKsearch');
    	$idFields = (isset($akConfig->Record->idFields) && !empty($akConfig->Record->idFields)) ? $akConfig->Record->idFields : 'id'; // Default is "id" Solr field
    	$this->uniqueKey = $idFields;
    	
    	$handlers = [
    			'select' => [
    					'fallback' => true,
    					'defaults' => ['fl' => '*,score'],
    					'appends'  => ['fq' => []],
    			],
    			'term' => [
    					'functions' => ['terms'],
    			],
    	];
    	
    	foreach ($this->getHiddenFilters() as $filter) {
    		array_push($handlers['select']['appends']['fq'], $filter);
    	}
    	
    	$connector = new \AkSearch\Backend\Solr\Connector($this->getSolrUrl(), new \VuFindSearch\Backend\Solr\HandlerMap($handlers), $this->uniqueKey);
    	$connector->setTimeout(isset($config->Index->timeout) ? $config->Index->timeout : 30);
    	
    	if ($this->logger) {
    		$connector->setLogger($this->logger);
    	}
    	
    	if ($this->serviceLocator->has('VuFind\Http')) {
    		$connector->setProxy($this->serviceLocator->get('VuFind\Http'));
    	}
    	
    	return $connector;
    }

}