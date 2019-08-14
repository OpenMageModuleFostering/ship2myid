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
CREATE TABLE {$this->getTable('clm24_myidcoupon')} (
  `entity_id` int(10) unsigned NOT NULL auto_increment,
  `rule_id` int(10) unsigned NOT NULL COMMENT 'Rule Id fo salesrule',
  `ship2myid_only` smallint(6) unsigned DEFAULT NULL COMMENT 'For ship2myid only',
  PRIMARY KEY  (`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8
")->endSetup();

