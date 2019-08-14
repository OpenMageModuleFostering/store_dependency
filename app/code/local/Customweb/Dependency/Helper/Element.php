<?php
/**
 ::[Header]::
* @category	Local
* @package	Customweb_Dependency
* @link		http://www.customweb.ch
*/

class Customweb_Dependency_Helper_Element extends Mage_Core_Helper_Abstract
{
	
	private $name = null;
	private $htmlId = null;
	private $elementId = null;
	private $params = null;
	
	/**
	 * Returns a HTML suffix that is added to every element of applicable admin forms.
	 * The suffix contains a select field that allows the user to select on what store view/website
	 * the element is dependent.
	 * 
	 * @param string $name Name of the element
	 * @param string $htmlId HTML ID of the element
	 * @param string $elementId ID of the element
	 * @param array  $params HTTP Request parameters
	 * @return string
	 */
	public function getElementSuffix($name,$htmlId,$elementId,$params)
	{
		$this->name = $name;
		$this->htmlId = $htmlId;
		$this->elementId = $elementId;
		$this->params = $params;
		$suffix = "";
		
		
		
		
		$suffix = $this->getSuffixForConfigElement();
		
		return $suffix;
	}
	
	
	
	
	/**
	 * Returns the select field for configuration elements.
	 *
	 * @return string HTML suffix
	 */
	private function getSuffixForConfigElement()
	{
		$suffix = "";
		
		if(isset($this->params['website']))
		{
			$websiteName = $this->params['website'];
			$storeName = (isset($this->params['store']) ? $this->params['store'] : null);
			$helper = Mage::helper('dependency/config');
		
			if(preg_match('/groups\[(.*)\]\[fields\]\[(.*)\]\[value/i',$this->name))
			{
				$dependency = Mage::getModel('dependency/config');
				$path = $helper->getPath($this->htmlId, $this->name);
				$scope = $helper->getConfigId($websiteName,$storeName);
				$dependsOn = $helper->getConfigDependency($path,$scope['scope'],$scope['scope_id']);
		
				$type = 'config';
		
				$selectName = preg_replace('/^(.*)\[value\]/i', '$1[source]' ,$this->name);
				$dependencyName = preg_replace('/^(.*)\[value\]/i', '$1[dependency]' ,$this->name);
		
				$selectHtml = $this->createScopeDependencySelect($selectName,$dependsOn);
		
				$suffix = '<input type="hidden" name="' . $dependencyName . '" value="1" />';
				$suffix .= '<div class="dependency-box"><div class="dependency-select">' . $selectHtml . '</div></div>';
			}
		}
		return $suffix;
	}
	
	/**
	 * Creates a select element that looks like the standard magento store switcher select
	 * for configurations.
	 * This code is partly taken from template/system/config/switcher.phtml
	 */
	private function createScopeDependencySelect($name,$selected)
	{
		$dependsOnWebsite = false;
		$dependsOnStore = false;
		$scope = $selected->getDependsOnScope();
		$helper = Mage::helper('dependency/config');
	
	
	
		if($scope == 'websites')
		{
			$dependsOnWebsite = $helper->getWebsiteName($selected->getDependsOnScopeId());
		}
		elseif ($scope == 'stores')
		{
			$dependsOnStore = $helper->getStoreName($selected->getDependsOnScopeId());
		}
	
		$html = '<select id="store_switcher" class="config-dependency-selector" dependencyfor="' . $this->htmlId .'" name="' . $name . '"  onchange="' . $this->getUpdateJsString() .'" >';
		foreach ($this->getStoreSelectOptions($dependsOnStore,$dependsOnWebsite) as $_value => $_option)
		{
			if (isset($_option['is_group']))
			{
				if ($_option['is_close'])
				{
					$html .= '</optgroup>';
				}
				else
				{
					$html .= '<optgroup label="' . $this->escapeHtml($_option['label']) . '" style="' . $_option['style'] . '">';
				}
				continue;
			}
			// Check for dependency
			$scope = $helper->getScope($_value);
			$selected->setDependsOnScope($scope['scope']);
			$selected->setDependsOnScopeId($scope['scope_id']);
			$optionClass = 'dependency-check-failed';
			if($helper->validateDependency($selected,false))
			{
				$optionClass = 'dependency-loop';
				if(Mage::helper('dependency/config')->doesYieldNoLoop($selected))
				{
					$optionClass = 'dependency-ok';
				}
			}
			$html .= '<option value="' . $this->escapeHtml($_value) . '" url="' . $_option['url'] . '" ' . ($_option['selected'] ? 'selected="selected"' : '') . ' class="' . $optionClass . '" ' . ' style="' . $_option['style'] . '">';
			$html .= $this->escapeHtml($_option['label']) . '</option>';
		}
		$html .= '</select>';
		return $html;
	}
	
	/**
	 * Taken from Mage_Adminhtml_Block_System_Config_Switcher
	 */
	private function getStoreSelectOptions($curStore,$curWebsite)
	{
		$section = $this->params['section'];
	
		$storeModel = Mage::getSingleton('adminhtml/system_store');
		/* @var $storeModel Mage_Adminhtml_Model_System_Store */
	
		$url = Mage::getModel('adminhtml/url');
	
		$options = array();
		$options['default'] = array(
				'label'    => Mage::helper('dependency')->__('No dependency'),
				'url'      => $url->getUrl('*/*/*', array('section'=>$section)),
				'selected' => !$curWebsite && !$curStore,
				'style'    => 'background:#ccc; font-weight:bold;',
		);
	
		foreach ($storeModel->getWebsiteCollection() as $website) {
			$websiteShow = false;
			foreach ($storeModel->getGroupCollection() as $group) {
				if ($group->getWebsiteId() != $website->getId()) {
					continue;
				}
				$groupShow = false;
				foreach ($storeModel->getStoreCollection() as $store) {
					if ($store->getGroupId() != $group->getId()) {
						continue;
					}
					if (!$websiteShow) {
						$websiteShow = true;
						$options['website_' . $website->getCode()] = array(
								'label'    => $website->getName(),
								'url'      => $url->getUrl('*/*/*', array('section'=>$section, 'website'=>$website->getCode())),
								'selected' => !$curStore && $curWebsite == $website->getCode(),
								'style'    => 'padding-left:16px; background:#DDD; font-weight:bold;',
						);
					}
					if (!$groupShow) {
						$groupShow = true;
						$options['group_' . $group->getId() . '_open'] = array(
								'is_group'  => true,
								'is_close'  => false,
								'label'     => $group->getName(),
								'style'     => 'padding-left:32px;'
						);
					}
					$options['store_' . $store->getCode()] = array(
							'label'    => $store->getName(),
							'url'      => $url->getUrl('*/*/*', array('section'=>$section, 'website'=>$website->getCode(), 'store'=>$store->getCode())),
							'selected' => $curStore == $store->getCode(),
							'style'    => '',
					);
				}
				if ($groupShow) {
					$options['group_' . $group->getId() . '_close'] = array(
							'is_group'  => true,
							'is_close'  => true,
					);
				}
			}
		}
	
		return $options;
	}
	
	private function getUpdateJsString()
	{
		$loopError = Mage::helper('dependency')->__("Can not depend on this store view. Loop detected.");
		$dependencyCheckError = Mage::helper('dependency')->__("A website cannot depend on a store view.");
	
		return 'updateDependencySelect(this,\'' . $this->htmlId .'\',false,\'' . $loopError . '\',\'' . $dependencyCheckError . '\')';
	}
	
	
	
	
	
	
	
}