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
		$akfilterSettings = $configLoader->get('Akfilter');
		
		// TODO: Check if this is really necessary
		// Unset default handler
		unset($this->defaultHandler);
		
		// TODO: Is this really necessary?
		$this->defaultHandler = 'AkfilterAll';
				
		// First unset from superior options which are set with parent::__construct($configLoader)
		unset($this->basicHandlers);
		
		// Iterate over the Akfilter settings and set the handlers for the searchbox:
		//   Filter values (basicHandlers[key:VALUE]) are prepended with filter key (basicHandlers[KEY:value])
		//   defined in Akfilter.ini and separated from it by colon (:). Filter values after the colon must be
		//   defined as search options in searchspecs.yaml
		foreach ($akfilterSettings as $akfilterKey => $akfilterValues) {
			$this->basicHandlers[$akfilterKey.':'.$akfilterValues->toptarget[0]] = $akfilterValues->toplabel[0];
			foreach ($akfilterValues->subtarget as $subtargetKey => $subtargetValue) {
				$this->basicHandlers[$akfilterKey.':'.$subtargetValue] = $akfilterValues->sublabel[$subtargetKey];
			}
		}
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
		//return 'akfilter-advanced';
		return false;
	}
	
	
}
?>