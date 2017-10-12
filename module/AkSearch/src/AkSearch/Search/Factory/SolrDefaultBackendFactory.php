<?php

namespace AkSearch\Search\Factory;

use VuFindSearch\Backend\Solr\Response\Json\RecordCollectionFactory;
use VuFindSearch\Backend\Solr\Connector;
use VuFindSearch\Backend\Solr\Backend;


class SolrDefaultBackendFactory extends \VuFind\Search\Factory\SolrDefaultBackendFactory
{
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        $this->searchConfig = 'searches';
        $this->searchYaml = 'searchspecs.yaml';
        $this->facetConfig = 'facets';
        
        /*
        echo '<pre>';
        print_r($this->test());
        echo '</pre>';
        */
        
        // TODO: Set $this->uniqueKey to a list of possible ID fields and create a custom method "retrieve" for VuFindSearch\Backend\Solr\Connector
        //       that queries these ID fields connected with OR.
        //$this->uniqueKey = 'acNo_txt';
    }
    
    public function test() {
        // Get AKsearch.ini
        $akConfig = $this->config->get('VuFind\Config')->get('AKsearch');
        return $akConfig;        
    }
}