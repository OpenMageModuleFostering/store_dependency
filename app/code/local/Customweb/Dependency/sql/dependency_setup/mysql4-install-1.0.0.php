<?php

/**
 ::[Header]::
 * @category	Local
 * @package	Customweb_Dependency
 * @link		http://www.customweb.ch
 */

$installer = $this;
$installer->startSetup();
$installer->run("CREATE TABLE `customweb_product_dependency` (
`dependency_id` INT NOT NULL AUTO_INCREMENT,
`attribute_id` INT NOT NULL ,
`store_id` INT NOT NULL ,
`entity_id` INT NOT NULL ,
`depends_on_store_id` INT NOT NULL ,
PRIMARY KEY ( `dependency_id` )
) ENGINE = InnoDB DEFAULT CHARSET=utf8;
");

$installer->run("CREATE TABLE `customweb_category_dependency` (
`dependency_id` INT NOT NULL AUTO_INCREMENT,
`attribute_id` INT NOT NULL ,
`store_id` INT NOT NULL ,
`entity_id` INT NOT NULL ,
`depends_on_store_id` INT NOT NULL ,
PRIMARY KEY ( `dependency_id` )
) ENGINE = InnoDB DEFAULT CHARSET=utf8;
");

$installer->run("CREATE TABLE `customweb_config_dependency` (
		`dependency_id` INT NOT NULL AUTO_INCREMENT,
		`path` VARCHAR(255) NOT NULL ,
		`scope` VARCHAR(8) NOT NULL ,
		`scope_id` INT NOT NULL ,
		`depends_on_scope` VARCHAR(8) NOT NULL ,
		`depends_on_scope_id` INT NOT NULL ,
		PRIMARY KEY ( `dependency_id` )
) ENGINE = InnoDB DEFAULT CHARSET=utf8;
");

$installer->endSetup();


