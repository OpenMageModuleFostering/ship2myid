<?php
/**
 * MapMyId Inc.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * 
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 *
 * @category  MapMyId Inc.
 * @package   Xclm24_Myidshipping
 * @version   1.3.0
 * @author    MapMyId Inc. Team <developer@ship2myid.com>
 * @copyright Copyright (C) 2013 MapMyId Inc. (http://www.mapmyid.com/)
 */

$installer = $this;
/* @var $installer Mage_Core_Model_Resource_Setup */
$this->startSetup()->run("
CREATE TABLE IF NOT EXISTS {$this->getTable('clm24_myidshipping_ordergrid')} (
  `entity_id` int(10) unsigned NOT NULL auto_increment,
  `order_id` int(10) unsigned NOT NULL COMMENT 'Order Id',  
  `real_order_id` varchar(255) DEFAULT NULL,
  `order_status_id` int(11) DEFAULT NULL COMMENT '0: Pending, 1: Accepted, 2: Rejected, 3: Completed',
  `order_group_id` varchar(255) DEFAULT NULL,
  `max_shipment` int(10) unsigned NOT NULL,
  `email` VARCHAR( 255 ) NULL DEFAULT NULL,
  `r_name` VARCHAR( 255 ) NULL DEFAULT NULL,
  `r_telephone` VARCHAR( 25 ) NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL COMMENT 'Created At',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT 'Updated At',
  PRIMARY KEY  (`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8
")->endSetup();

