<?php

class Customweb_Dependency_TestController extends Mage_Core_Controller_Front_Action
{
	public function indexAction(){
		$model = new Mage_Core_Model_Config();
		//$model->saveConfig('esr/account_information/deposit_for', "Geänadert auf website level", 'websites', 2);
		//$model->saveConfig('esr/account_information/deposit_for', "geändert auf store level", 'stores', 2);
		$model->saveConfig('esr/account_information/deposit_for', "default 88");
		die("fertig");
	}
}
