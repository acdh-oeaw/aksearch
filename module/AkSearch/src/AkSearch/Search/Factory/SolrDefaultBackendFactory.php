<?php

namespace AkSearch\Search\Factory;

use VuFindSearch\Backend\Solr\Response\Json\RecordCollectionFactory;
use VuFindSearch\Backend\Solr\Connector;
use VuFindSearch\Backend\Solr\Backend;


class SolrDefaultBackendFactory extends \VuFind\Search\Factory\SolrDefaultBackendFactory implements \Zend\ServiceManager\ServiceLocatorAwareInterface {
    
    use \Zend\ServiceManager\ServiceLocatorAwareTrait;
    
    protected $akConfig;
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        
        $this->searchConfig = 'searches';
        $this->searchYaml = 'searchspecs.yaml';
        $this->facetConfig = 'facets';

        // TODO: Set $this->uniqueKey to a list of possible ID fields ???and create a custom method "retrieve" for VuFindSearch\Backend\Solr\Connector???
        //       that queries these ID fields connected with OR.
        //       ATTENTION: We cannot get AKsearch.ini here. We probably have to create a custom AbstractSolrBackendFactory
        //$this->uniqueKey = 'acNo_txt'; // WORX
    }

}