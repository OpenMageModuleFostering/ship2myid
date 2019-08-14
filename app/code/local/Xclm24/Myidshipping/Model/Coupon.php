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

/**
 * Myidshipping Coupon Model
 * 
 */
class Xclm24_Myidshipping_Model_Coupon extends Mage_SalesRule_Model_Coupon
{
    /**
     * Constructor for Coupon model
     * 
     */    
    public function _construct()
    {
        parent::_construct();
        $this->_init('myidshipping/coupon');
    }

    /**
     * Load Model By Rule Id
     * @param   Strinng $rule_entity_id
     * @return  Xclm24_Myidshipping_Model_Shipping
     * 
     */       
    public function loadByRuleId($rule_entity_id)
    {
        $this->_getResource()->loadByRuleId($this, $rule_entity_id);
        return $this;
    }
}