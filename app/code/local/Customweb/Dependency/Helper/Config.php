<?php
/**
 ::[Header]::
 * @category	Local
 * @package	    Customweb_Dependency
 * @link		http://www.customweb.ch
 */

class Customweb_Dependency_Helper_Config extends Mage_Core_Helper_Abstract
{
	/**
	 * Get all edges as a adjacency list where result[a] contains
	 * all the nodes that depend on a
	 * 
	 * @param string $path
	 * @return array
	 */
	public function getDependentConfigEdges($path)
	{
		$edges = array();
		$attributeCollection = Mage::getModel('dependency/config')->getCollection();
		$attributeCollection->addFieldToFilter('path', $path);

		foreach($attributeCollection as $att)
		{
			$edges[$att->getDependsOnScope() . '_' . $att->getDependsOnScopeId()][] = $att->getScope() . '_' . $att->getScopeId();
		}
		return $edges;
	}


	/**
	 * Get all edges as a adjacency list where result[a] contains
	 * all the nodes that a depends on
	 * 
	 * @param string $path
	 * @return array
	 */
	public function getDependeeConfigEdges($path)
	{
		$edges = array();
		$attributeCollection = Mage::getModel('dependency/config')->getCollection();
		$attributeCollection->addFieldToFilter('path', $path);

		foreach($attributeCollection as $att)
		{
			$edges[$att->getScope() . '_' . $att->getScopeId()] = $att->getDependsOnScope() . '_' . $att->getDependsOnScopeId();
		}
		return $edges;
	}

	/**
	 * Returns the dependency of an configuration entry.
	 * 
	 * @param string $path
	 * @param string $scope
	 * @param string $scope_id
	 * @return Customweb_Dependency_Model_Config
	 */
	public function getConfigDependency($path,$scope,$scope_id)
	{
		$configCollection = Mage::getModel('dependency/config')->getCollection();
		$configCollection->addFieldToFilter('path', $path)
		->addFieldToFilter('scope', $scope)
		->addFieldToFilter('scope_id', intval($scope_id));
		$configDependency = $configCollection->getFirstItem();
		if(!$configDependency->getId())
		{
			$configDependency = Mage::getModel('dependency/config');
			$configDependency->setPath($path);
			$configDependency->setScope($scope);
			$configDependency->setScopeId($scope_id);
		}
		return $configDependency;
	}

	/**
	 * Translates between website and store names and corresponding 
	 * IDs.
	 * 
	 * @param string $websiteName
	 * @param string $storeName
	 */
	public function getConfigId($websiteName, $storeName)
	{
		$this->initTranslateArrays();

		if($storeName == null)
		{
			return $this->websiteNameToCode[$websiteName];
		}
		else
		{
			return $this->storeNameToCode[$storeName];
		}
			
	}

	/**
	 * Translates the select identifier value to a pair of
	 * scope and scopeId.
	 *
	 * This code is based on Mage_Adminhtml_Model_Config_Data::_getScope()
	 *
	 * @param string $string
	 * @return array
	 */
	public function getScope($string)
	{
		try {
			$parts = explode('_',$string,2);
		} catch (Exception $e) {
			throw new Exception(print_r($string,true) . $e->getMessage());
		}

		if(count($parts) == 2)
		{
			$scope = $parts[0] . 's';
			$scopeCode = $parts[1];
			$scopeId = (int)Mage::getConfig()->getNode($scope . '/' . $scopeCode . '/system/' . $parts[0] . '/id');
		}
		else
		{
			$scope = 'default';
			$scopeId = 0;
		}
		return array('scope' => $scope, 'scope_id' => $scopeId);
	}

	/**
	 * Splits a website/store identifier string of the form "stores_2" into an array.
	 * 
	 * @param string $string website/store identifier 
	 * @return array
	 */
	public function splitScopeString($string)
	{
		$parts = explode('_',$string,2);
		return array('scope' => $parts[0], 'scope_id' => $parts[1]);
	}


	/**
	 * Create the mappings between website/store identifier strings and the website/store
	 * codes.
	 */
	private function initTranslateArrays()
	{
		$storeModel = Mage::getSingleton('adminhtml/system_store');

		if($this->websiteNameToCode == null)
		{
			$this->websiteNameToCode = array();
			foreach ($storeModel->getWebsiteCollection() as $website)
			{
				$this->websiteNameToCode[$website->getCode()] = $this->getScope('website_' . $website->getCode());
				$scope = $this->getScope('website_' . $website->getCode());
				$this->websiteCodeToName[$scope['scope_id']] = $website->getCode();
			}
		}
		if($this->storeNameToCode == null)
		{
			$this->storeNameToCode = array();
			foreach ($storeModel->getStoreCollection() as $store)
			{
				$this->storeNameToCode[$store->getCode()] = $this->getScope('store_' . $store->getCode());
				$scope = $this->getScope('store_' . $store->getCode());
				$this->storeCodeToName[$scope['scope_id']] = $store->getCode();
			}
		}
	}

	/**
	 * Checks for loops if we add the given new dependency.
	 *
	 * @param mixed $dependency
	 * @param string $type
	 * @return boolean True if the dependency graph does not contain a cylce starting at the specified node
	 */
	public function doesYieldNoLoop($dependency){

		$edgesToDependent = $this->getDependentConfigEdges(
				$dependency->getPath());

		$node = $dependency->getScope() . '_' . $dependency->getScopeId();
		$newDependency = $dependency->getDependsOnScope() . '_' . $dependency->getDependsOnScopeId();



		// Only add the dependency if it does not already exist.
		if(isset($edgesToDependent[$newDependency]))
		{
			if(!in_array($node,
					$edgesToDependent[$newDependency]))
			{
				$edgesToDependent[$newDependency][] = $node;
			}
		}
		else
		{
			$edgesToDependent[$newDependency] = array($node);
		}

		return Mage::helper('dependency')->dfs($node,$edgesToDependent);
	}

	/**
	 * Returns the path of the config value extracted from the html id and the element name
	 * of the respective form element.
	 *
	 * @param string $htmlId
	 * @param string $elementName
	 * @return string
	 */
	public function getPath($htmlId, $elementName)
	{
		preg_match('/groups\[(.*)\]\[fields\]\[(.*)\]\[value/i', $elementName, $m);

		$group = $m[1];
		$entry = $m[2];
		preg_match('/(.*)\_' . $m[1] . '/', $htmlId, $m);
		$base = $m[1];

		return $this->createPath($base,$group,$entry);
	}

	/**
	 * Delete the dependency from the config value given, if any.
	 *
	 * @param string $path
	 * @param string $scope
	 * @param string $scopeId
	 */
	public function deleteDependenyOf($path, $scope, $scopeId)
	{
		$dependency = $this->getConfigDependency($path,$scope,$scopeId);
		if($dependency->getId())
		{
			$dependency->delete();
		}
	}

	/**
	 * Creates the correct path to the config value. Especially it handles custom
	 * defined field paths.
	 * Code taken from Mage_Adminhtml_Model_Config_Data::save()
	 *
	 * @param string $section
	 * @param string $group
	 * @param string $name
	 * @return string
	 */
	public function createPath($section,$group,$name)
	{
		$path = $section . '/' . $group . '/' . $name;
		$fieldConfig = $this->getSections()->descend($section.'/groups/'.$group.'/fields/'.$name);

		/**
		 * Look for custom defined field path
		*/
		if (is_object($fieldConfig)) {
			$configPath = (string)$fieldConfig->config_path;
			if (!empty($configPath) && strrpos($configPath, '/') > 0) {
				$path = $configPath;
			}
		}
		return $path;
	}

	/**
	 * Returns the store name based on the store code
	 * @param int $code
	 * @return string
	 */
	public function getStoreName($code)
	{
		$this->initTranslateArrays();
		return $this->storeCodeToName[$code];
	}

	/**
	 * Returns the website name based on the website code.
	 * @param int $code
	 * @return string
	 */
	public function getWebsiteName($code)
	{
		$this->initTranslateArrays();
		return $this->websiteCodeToName[$code];
	}

	/**
	 * Makes sure the dependency is valid. This does not check for loops in the
	 * dependency graphs it only makes sure no dependencies from website level to store level
	 * are possible.
	 * 
	 * @param Customweb_Dependency_Model_Config $dependency
	 * @param string $throwException True(default) the method throws an exception, false the method returns false on invalid dependencies.
	 * @throws Exception
	 * @return boolean
	 */
	public function validateDependency(Customweb_Dependency_Model_Config $dependency, $throwException=true)
	{
		if($dependency->getScope() == 'websites' && $dependency->getDependsOnScope() == 'stores')
		{
			if($throwException)
			{
				throw new Exception(Mage::helper('dependency')->__("A website cannot depend on a store view."));
			}
			return false;
		}
		return true;
	}

	/**
	 * Returns all configuration sections.
	 */
	private function getSections()
	{
		if($this->sections == null){
			$this->sections = Mage::getModel('adminhtml/config')->getSections();
		}
		return $this->sections;
	}

	protected $storeNameToCode = null;
	protected $websiteNameToCode = null;
	protected $storeCodeToName = null;
	protected $websiteCodeToName = null;
	protected $sections = null;

}