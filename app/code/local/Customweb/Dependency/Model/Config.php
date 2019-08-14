<?php

/**
 ::[Header]::
 * @category	Local
 * @package	Customweb_Dependency
 * @link		http://www.customweb.ch
 */

class Customweb_Dependency_Model_Config extends Mage_Core_Model_Abstract
{
	protected function _construct()
	{
		$this->_init('dependency/config');
	}

}