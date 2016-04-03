<?php
namespace AkSearch\Search\Akfilter;
class Options extends \VuFind\Search\Solr\Options {
	
	/**
	 * Constructor
	 *
	 * @param \VuFind\Config\PluginManager $configLoader Config loader
	 */
	public function __construct(\VuFind\Config\PluginManager $configLoader) {
		parent::__construct($configLoader);

		unset($this->defaultHandler);
		$this->defaultHandler = 'AkfilterAll';
		
		// Keys must be defined as search-options in searchspecs.yaml
		unset($this->basicHandlers); // First unset from superior options set with parent::__construct($configLoader)
		$this->basicHandlers['AkfilterAll'] = 'DVD all fields';
		$this->basicHandlers['AkfilterTitle'] = 'DVD Title';
		$this->basicHandlers['AkfilterPersons'] = 'DVD Person';
		
		unset($this->advancedHandlers);
		$this->advancedHandlers['AkfilterAll'] = 'DVD all fields';
		$this->advancedHandlers['AkfilterTitle'] = 'DVD Title';
		$this->advancedHandlers['AkfilterPersons'] = 'DVD Person';
	}
	
	/**
	 * Return the route name for the search results action.
	 *
	 * @return string
	 */
	public function getSearchAction()
	{
		return 'akfilter-search';
	}
	
	/**
	 * Return the route name of the action used for performing advanced searches.
	 * Returns false if the feature is not supported.
	 *
	 * @return string|bool
	 */
	public function getAdvancedSearchAction()
	{
		return 'akfilter-advanced';
		//return false;
	}
	
	
}
?>