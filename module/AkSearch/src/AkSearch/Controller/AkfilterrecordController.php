<?php

namespace AkSearch\Controller;
use VuFind\Controller\AbstractRecord as DefaultAbstractRecord;

class AkfilterrecordController extends DefaultAbstractRecord
{
    /**
     * Constructor
     */
    public function __construct()
    {
    	// CALLED
    	//echo '<br>AkSearch\Controller\AkfilterrecordController -> __construct';
    	 
        // Override some defaults:
        $this->searchClassId = 'Akfilter';
        $this->fallbackDefaultTab = 'Description';

        // Call standard record controller initialization:
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
}