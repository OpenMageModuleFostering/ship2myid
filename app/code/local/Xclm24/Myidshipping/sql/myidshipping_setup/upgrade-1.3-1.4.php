<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Kit Lee
 * Date: 21/01/13
 * Time: 11:15 AM
 * To change this template use File | Settings | File Templates.
 */
$installer = $this;
/* @var $installer Mage_Core_Model_Resource_Setup */
$this->startSetup()->run("
ALTER TABLE  {$this->getTable('clm24_myidshipping_ordergrid')} ADD  `email` VARCHAR( 255 ) NULL DEFAULT NULL AFTER  `max_shipment` ,
ADD  `r_name` VARCHAR( 255 ) NULL DEFAULT NULL AFTER  `email` ,
ADD  `r_telephone` VARCHAR( 25 ) NULL DEFAULT NULL AFTER  `r_name`    
")->endSetup();

