<?php
namespace AkSearch\Controller;

class AkfilterController extends \VuFind\Controller\AbstractSearch {
	
    /**
     * Constructor
     */
    public function __construct() {    	
        $this->searchClassId = 'Akfilter';
        parent::__construct();
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
    	// Search action to call:
    	return $this->resultsAction();
    }
    

}