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

class Xclm24_Myidshipping_Model_Resource_Shipping extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Define main table
     *
     */
    protected function _construct()
    {
        $this->_init('myidshipping/shipping', 'entity_id');
    }

    public function loadByOrderId(Xclm24_Myidshipping_Model_Shipping $myidshipping, $order_entity_id)
    {
        $adapter = $this->_getReadAdapter();

        $select = $adapter->select()
            ->from($this->getMainTable())
            ->where($this->getMainTable().".order_id=?", $order_entity_id);

        $myshipping_id = $adapter->fetchOne($select);
        if ($myshipping_id) {
            $this->load($myidshipping, $myshipping_id);
        }

        return $this;
    }
}
