<?php
namespace AkSearch\Search\Akfilter;

class Params extends \VuFind\Search\Solr\Params {
	
	protected $type = null;
	
	public function __construct($options, \VuFind\Config\PluginManager $configLoader) {
        parent::__construct($options, $configLoader);
	}
	
	
	
	

	// IMPORTANT:
	// These methods are important that pagination keeps the right searchHandler:
	//  - setType (see initBasicSearch())
	//  - getType
	//  - initBasicSearch (setType is used there - that's the key to right pagination!)
	public function setType($type) {
		$this->type = $type;
	}
	public function getType() {
		return $this->type;
	}
	protected function initBasicSearch($request)
	{
		// IMPORTANT for pagination - Begin
		$this->setType($request->get('type'));
		// IMPORTANT for pagination - End
		
		
		// ORIGINAL - Begin
		if (is_null($lookfor = $request->get('lookfor'))) {
			return false;
		}
		// If lookfor is an array, we may be dealing with a legacy Advanced
		// Search URL.  If there's only one parameter, we can flatten it,
		// but otherwise we should treat it as an error -- no point in going
		// to great lengths for compatibility.
		if (is_array($lookfor)) {
			if (count($lookfor) > 1) {
				throw new \Exception("Unsupported search URL.");
			}
			$lookfor = $lookfor[0];
		}
	
		// Flatten type arrays for backward compatibility:
		$handler = $request->get('type');
		if (is_array($handler)) {
			$handler = $handler[0];
		}
	
		// Set the search:
		$this->setBasicSearch($lookfor, $handler);
		return true;
		// ORIGINAL - End
	}
	
	
	
}

?>