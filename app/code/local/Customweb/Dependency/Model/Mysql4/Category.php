<?php
/**
 ::[Header]::
 * @category	Local
 * @package	Customweb_Dependency
 * @link		http://www.customweb.ch
 */

class Customweb_Dependency_Model_Mysql4_Category extends Mage_Core_Model_Mysql4_Abstract{
    protected function _construct()
    {
        $this->_init('dependency/category', 'dependency_id');
    }   
} 
