<?php
/**
 ::[Header]::
 * @category	Local
 * @package	Customweb_Dependency
 * @link		http://www.customweb.ch
 */

class Customweb_Dependency_Model_ConfigObserver
{
	/**
	 * The method is registered to the event 'model_config_data_save_before'. It updates
	 * the dependency graph.
	 * 
	 * @param Varien_Event_Observer $observer
	 */
	public function configSaveBefore(Varien_Event_Observer $observer)
	{		
		$event = $observer->getEvent();
		$config = $event->getObject();
	
		$section = $config->getSection();
		$website = $config->getWebsite();
		$store   = $config->getStore();
		$groups  = $config->getGroups();
		$scope   = $config->getScope();
		$scopeId = $config->getScopeId();	
		$this->handleConfigs = array();
		
		$write = Mage::getSingleton('core/resource')->getConnection('core_write');
		$write->beginTransaction();
		
		
		try
		{
			foreach ($groups as $group => $groupData) 
			{
				foreach($groupData['fields'] as $name => $data)
				{
					if(isset($data['dependency']))
					{
						$path = $this->getHelper()->createPath($section, $group, $name);
						$dependency = $this->getHelper()->getConfigDependency($path,$scope,$scopeId);
						$source = 'default';
						
						if(isset($data['source']))
						{
							$source = $this->getFirstElementIfArray($data['source']);
						}
						
						if(isset($data['inherit']) || $source == 'default')
						{
							$dependency->delete();
	
							// Config values that are inherited have to be rememered, for later processing
							$this->handleConfigs[] = array('path' => $path, 'scope' => $scope, 'scopeId' => $scopeId);
							
						}
						else
						{
							// Create dependency
							$scopeInfo = $this->getHelper()->getScope($source);
							$dependency->setDependsOnScope($scopeInfo['scope']);
							$dependency->setDependsOnScopeId($scopeInfo['scope_id']);
							$this->getHelper()->validateDependency($dependency);
							$dependency->save();
							
							
							// Save a config value
							//$configModel = new Mage_Core_Model_Config();
							//$configModel ->saveConfig('general/country/default', "US", $scope, $scopeId);
						}	
					}
				}
			}
			$write->commit();
		}
		catch(Exception $e)
		{
			while($write->getTransactionLevel() > 0)
			{
				$write->rollback();
			}
			throw $e;
		}
	}
	
	/**
	 * This method is registered to the event 'core_config_data_delete_after'. All dependencies
	 * are resolved after deleting and inherited values are updated.
	 * 
	 * @param Varien_Event_Observer $observer
	 */
	public function configDeleteAfter(Varien_Event_Observer $observer)
	{
		$this->configSaveAfter($observer);
	}
	
	/**
	 * This method is registered to the event 'core_config_data_save_after'. All dependencies
	 * are resolved after saving and inherited values are updated.
	 * 
	 * @param Varien_Event_Observer $observer
	 */
	public function configSaveAfter(Varien_Event_Observer $observer)
	{
		// We keep track of wheter we are already in the dependency updating process
		static $configUpdateInProgress = false;
		
		if(!$configUpdateInProgress)
		{
			$configUpdateInProgress = true;
			$configData = $observer->getEvent()->getConfigData();
			
			$path = $configData->getPath();
			$scope = $configData->getScope();
			$scopeId = $configData->getScopeId();
		
			$this->handleConfigs[] = array('path' => $path, 'scope' => $scope, 'scopeId' => $scopeId);
			$this->saveUpdateConfig($this->handleConfigs);
			
			$configUpdateInProgress = false;
		}
	}
	
	/**
	 * This method listens to the event 'core_model_config_save_before'.
	 * This is a CUSTOM EVENT that is introduced in local/Mage/Core/Model/Config.php to 
	 * handle changes in config values from code and not the UI.
	 * The dependency for the changed config value is removed if one exists and dependent values 
	 * are updateted.
	 * 
	 * @param Varien_Event_Observer $observer
	 */
	public function coreConfigSaveAfter(Varien_Event_Observer $observer)
	{	
		$path = $observer->getPath();
		$scope = $observer->getScope();
		$scopeId = $observer->getScopeId();
		
		$write = Mage::getSingleton('core/resource')->getConnection('core_write');
		$write->beginTransaction();
		
		try
		{
			$this->getHelper()->deleteDependenyOf($path,$scope,$scopeId);
			$this->saveUpdateConfig(array('path' => $path, 'scope' => $scope, 'scopeId' => $scopeId));
		}
		catch(Exception $e)
		{
			while($write->getTransactionLevel() > 0)
			{
				$write->rollback();
			}
			throw $e;
		}
		
		if($write->getTransactionLevel() >= 1)
		{
			$write->commit();
		}
	}
	
	/**
	 * Handles all the config values we need to update.
	 * @param array $configs
	 */
	protected function saveUpdateConfig($configs)
	{
		foreach($configs as $config)
		{
			$path = $config['path'];
			$scope = $config['scope'];
			$scopeId = $config['scopeId'];
			$allConfigData = $this->getAllConfigs($path);
			
			if($scope == 'default' || preg_match('/website/i',$scope) || !isset($allConfigData[$scope . '_' . $scopeId]))
			{
				foreach($allConfigData as $entity)
				{
					$this->updateAllChildren($path, $entity, $allConfigData);
				}
			}
			else
			{
				$entity = $allConfigData[$scope . '_' . $scopeId];		
				$this->updateAllChildren($path, $entity, $allConfigData);
			}
				
			$this->saveAllConfigData($allConfigData);
		}
	}
	
	/**
	 * Updates all children of $possibleChild in the dependency graph.
	 * 
	 * @param string $path
	 * @param Mage_Core_Config_Data $possibleChild
	 * @param array $allConfigData
	 */
	protected function updateAllChildren($path, $possibleChild, $allConfigData)
	{
		$edgesToDependent = $this->getHelper()->getDependentConfigEdges($path);
		$edgesFromDependent = $this->getHelper()->getDependeeConfigEdges($path);
		$start = $possibleChild->getScope() . "_" . $possibleChild->getScopeId();
		$updateRoot  = false;
		$value = $possibleChild->getValue();
		
		// If current node has a parent in the dependency graph
		if(isset($edgesFromDependent[$start]))
		{
			$parent = $edgesFromDependent[$start];
			$value = $this->getParentValue($parent,$allConfigData,$path);
			$updateRoot = true;
		}
		
		$this->dfs($start, $allConfigData, $edgesToDependent, $value ,$updateRoot);	
	}
	
	private function dfs($node, $allNodes, $edges, $value, $update)
	{
		static $visited = array();
		if(array_key_exists($node,$visited) && $visited[$node] == 1)
		{
			throw new Exception(Mage::helper('dependency')->__("Dependency loop detected."));
		}
		else
		{
			$visited[$node] = 1;
			if($update)
			{
				$s = $allNodes[$node];
				if($s->getValue() != $value)
				{
					$s->setValue($value);
				}		
			}
			
			if(isset($edges[$node]))
			{	
				foreach($edges[$node] as $nextNode)
				{
					$this->dfs($nextNode, $allNodes, $edges, $value, true);
				}
			}
		}
		
		$visited[$node] = 0;
	}
	
	/**
	 * Returns the value of the $parent node. This is a bit tricky as config values
	 * that use default values do no longer exist in the database.
	 * 
	 * @param string $parent
	 * @param array $allConfigData
	 * @param string $path
	 * @return mixed
	 */
	private function getParentValue($parent, $allConfigData, $path)
	{
		// If the parent uses default no config value exists.
		if(!isset($allConfigData[$parent]))
		{
			$scope = $this->getHelper()->splitScopeString($parent);
		
			if(preg_match("/store/i", $scope['scope']))
			{
				$websiteId = $this->storeToWebsite($scope['scope_id']);
					
				// If the website config does not exist, back up to the default value.
				if(!isset($allConfigData['websites_' . $websiteId]))
				{
					// If there is no default value in the db, backup to the confg xml
					if(!isset($allConfigData['default_0']))
					{
						$value = Mage::getStoreConfig($path);
					}
					else 
					{
						$value = $allConfigData['default_0']->getValue();
					}
				}
				else
				{
					$value = $allConfigData['websites_' . $websiteId]->getValue();
				}
			}
			else
			{
				$value = $allConfigData['default_0']->getValue();
			}
		}
		else
		{
			$value = $allConfigData[$parent]->getValue();
		}
		return $value;
	}
	
	private function getFirstElementIfArray($possibleArray)
	{
		return is_array($possibleArray) ? $possibleArray[0] : $possibleArray;	
	}
	
	/**
	 * Returns an array of all the stored config values for a given path
	 * @param string $path
	 * @return array
	 */
	private function getAllConfigs($path)
	{
		$allConfigs = array();
		$configDataCollection = Mage::getModel('core/config_data')->getCollection()
			->addFieldToFilter('path', array('like' => "%" . $path . '%'));
		
		foreach ($configDataCollection as $data) {
			$allConfigs[$data->getScope() . '_' . $data->getScopeId()] = $data;
		}

		return $allConfigs;
	}
	
	/**
	 * Returns the website id for a given store id;
	 * @param int $id
	 */
	private function storeToWebsite($id)
	{
		if($this->storeToWebsiteMap == null)
		{
			$this->storeToWebsiteMap = array();
			$storeModel = Mage::getSingleton('adminhtml/system_store');
			foreach ($storeModel->getStoreCollection() as $store) {
				$this->storeToWebsiteMap[$store->getId()] = $store->getWebsiteId();
			}
		}
		return $this->storeToWebsiteMap[$id];
	}
	
	/**
	 * Stores all the configuration data to the database.
	 * @param array $allConfigData
	 */
	private function saveAllConfigData($allConfigData)
	{
		foreach($allConfigData as $data){
			$data->save();
		}
	}
	
	private function getHelper()
	{
		if($this->helper == null)
		{
			$this->helper = Mage::helper('dependency/config');
		}
		return $this->helper;
	}
	
	private $helper = null;
	private $handleConfigs = array();
	private $storeToWebsiteMap = null;
}