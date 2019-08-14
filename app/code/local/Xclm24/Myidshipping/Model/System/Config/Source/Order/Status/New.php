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
 * Order Statuses source model
 */
class Xclm24_Myidshipping_Model_System_Config_Source_Order_Status_New extends Mage_Adminhtml_Model_System_Config_Source_Order_Status
{
    protected $_stateStatuses = array(
        Mage_Sales_Model_Order::STATE_NEW,
        Mage_Sales_Model_Order::STATE_PROCESSING
    );
}
