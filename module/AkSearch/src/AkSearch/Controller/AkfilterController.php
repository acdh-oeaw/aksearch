<?php

namespace AkSearch\Controller;

class AkfilterController extends \VuFind\Controller\AbstractSearch
{
	
	/**
	 * Search class family to use.
	 *
	 * @var string
	 */
	//protected $searchClassId = 'Akfilter';
	
    /**
     * Constructor
     */
    public function __construct() {
    	// CALLED
    	//echo '<br>AkSearch\Controller\AkfilterController -> __construct';
    	
        $this->searchClassId = 'Akfilter';
        parent::__construct();
        //'WorldCat'
    }
    
    /**
     * Is the result scroller active?
     *
     * @return bool
     */
    protected function resultScrollerActive()
    {
    	$config = $this->getServiceLocator()->get('VuFind\Config')->get('Akfilter');
    	return (isset($config->Record->next_prev_navigation) && $config->Record->next_prev_navigation);
    }
    
    /**
     * Home action
     *
     * @return mixed
     */
    public function homeAction()
    {
    	// Set up default parameters:
    	return $this->createViewModel();
    }
    
    /**
     * Search action -- call standard results action
     *
     * @return mixed
     */
    
    public function searchAction()
    {
    	return $this->resultsAction();
    }
    

}