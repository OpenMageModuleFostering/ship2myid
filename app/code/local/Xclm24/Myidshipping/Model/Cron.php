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
 * Myidshipping CRON Model
 * 
 */

class Xclm24_Myidshipping_Model_Cron extends Mage_Core_Model_Abstract 
{

    /**
     * Constructor for Cron
     * 
     */
    
    protected function _construct() {
        parent::_construct();
        $this->_init('myidshipping/cron');
    }

    /**
     * Get Order updates from Ship2MyId Server for pending orders
     * 
     */
    
    public function scheduler() {
        $_cml24Helper = Mage::Helper('myidshipping');
        $clm24_token = $_cml24Helper->getMyIDSession();

        // 0: Pending, 1: Accepted, 2: Rejected, 3: Completed
        $clm24_model = Mage::getModel("myidshipping/ordergrid")->getCollection()
	->addFieldToFilter('main_table.order_status_id', array('eq' => 0));

        $orderData = $clm24_model->getData();
        foreach ($orderData as $result) {
            $entityId = $result['entity_id'];
            $orderId = $result['order_id'];
            //$customerId       = $result['customer_id'];
            $realorderId = $result['real_order_id'];
            if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
                Mage::log('orderid:' . $orderId);
                Mage::log('orderid:' . $realorderId);
            }
            if (!empty($realorderId)) {
	$_clm24Order = $_cml24Helper->checkOrder($clm24_token, $realorderId);
	if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
	    Mage::log('realorderId:');
	    Mage::log('$realorderId: ' . implode(',', $_clm24Order));
	    Mage::log('clmorder array:' . zend_debug::dump($_clm24Order));
	}
	$tele = "NIL";
	if (isset($_clm24Order["status"])) {
	    $_message = $_clm24Order["message"];
	        Mage::Log(__METHOD__ . ' :: ' . __LINE__ . " " . $_message);
	} else {
	    // Prepare the Order Object
	    // Load the Order
	    $order = Mage::getModel('sales/order')
	            ->getCollection()
	            ->addAttributeToFilter('state', array('neq' => Mage_Sales_Model_Order::STATE_CANCELED))
	            ->addAttributeToFilter('increment_id', $orderId)
	            ->getFirstItem();

	    $_order_status_id = 0;

	    // Do we have any response
	    if ($order->getId()):
	        if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
	            Mage::log('Order found: ' . $order->getId());
	        }
	        if ($_clm24Order["is_order_accepted"] == "true") {
	            $_order_status_id = 1;
	            $newOrderStatus = Mage::getStoreConfig('clm24core/shippings/order_status');
	            if ($newOrderStatus == 'processing') {
		$order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true)->save();
	            }
	            // We need to update the ship to Address too
	            $clm24_shippingmodel = Mage::getModel('sales/order_address')->load($order->getShippingAddressId());
	            $regionModel = Mage::getModel('directory/region')->loadByCode($_clm24Order["state"], $_clm24Order["countryCode"]);
	            $regionId = $regionModel->getId();

	            if ($_clm24Order["phoneNumber"])
		$tele = $_clm24Order["phoneNumber"];
	            else
		$tele = "NIL";

	            $_shipping = Array
		(
		//            [address_id] => 0
		"entity_id" => $order->getShippingAddressId(),
		"parent_id" => $clm24_shippingmodel->getData("parent_id"),
		"firstname" => ($_clm24Order["receiver_first_name"] ? $_clm24Order["receiver_first_name"] : '' ),
		"lastname" => ($_clm24Order["receiver_last_name"] ? $_clm24Order["receiver_last_name"] : ''),
		"company" => "",
		"street" => $_clm24Order["address_1"] . ' ' . $_clm24Order["address_2"],
		"city" => $_clm24Order["city"],
		"region_id" => $regionId,
		"region" => $_clm24Order["stateName"],
		"postcode" => $_clm24Order["zipcode"],
		"country_id" => $_clm24Order["countryCode"],
		"telephone" => $_clm24Order["receiver_telephone"],
		"fax" => "",
		"address_type" => "shipping"
	            );
	            if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
		Mage::log('shipping array:' . $_shipping);
	            }
	            $clm24_shippingmodel->setData($_shipping)->save();

	            $resource = Mage::getSingleton('core/resource');
	            $writeConnection = $resource->getConnection('core_write');
	            $table = $resource->getTableName('myidshipping/shipping');

	            $queryfirst = "Update " . $table . " set value='" . $_clm24Order["receiver_first_name"] . "' where order_grid_id ='" . $entityId . "' and (attribute ='firstname' or attribute ='receiver_first_name')";
	            if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
		Mage::log('update_first:' . $queryfirst);
	            }
	            $writeConnection->query($queryfirst);

	            $querylast = "Update " . $table . " set value='" . $_clm24Order["receiver_last_name"] . "' where order_grid_id ='" . $entityId . "' and (attribute ='lastname' or attribute ='receiver_last_name')";
	            if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
		Mage::log('update_last:' . $querylast);
	            }
	            $writeConnection->query($querylast);

	            $query = "Update " . $table . " set value='" . $tele . "' where order_grid_id ='" . $entityId . "' and attribute ='r_telephone' ";
	            if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
		Mage::log('update_telephone:' . $query);
	            }
	            $writeConnection->query($query);
	        }

	        if ($_clm24Order["is_order_rejected"] == "true") {
	            $_order_status_id = 2;
	            /**
	             * change order status to 'Holded' or 'Canceled' according to config
	             */
	            $rejectedOrderStatus = Mage::getStoreConfig('clm24core/shippings/rejected_order_status');
	            if ($rejectedOrderStatus == 'canceled') {
		$order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, $_clm24Order["receiver_rejected_note"])->save();
	            } else {
		$order->setState(Mage_Sales_Model_Order::STATE_HOLDED, true, $_clm24Order["receiver_rejected_note"])->save();
	            }
	        }

	        // Save the data
	        $_data = array(
	            "entity_id" => $entityId,
	            "order_status_id" => $_order_status_id,
	            "r_name"=> $_clm24Order["receiver_first_name"].' '.$_clm24Order["receiver_last_name"], 
	            "r_telephone"=> $tele, 
	            "updated_at" => date('Y-m-d H:i:s')
	        );

	        $clm24_model = Mage::getModel("myidshipping/ordergrid")
		->setData($_data)
		->save();
	    else:
	        Mage::log('order not found');
	    endif;
	}
            } else {
	
            }
        }

        $_cml24Helper->closeMyIDSession($clm24_token);
    }

    /**
     * Cancel Order at Ship2MyId Server
     * 
     */    
    public function croncancelorder() {
        $_cml24Helper = Mage::Helper('myidshipping');
        $clm24_token = $_cml24Helper->getMyIDSession();

        // 0: Pending, 1: Accepted, 2: Rejected, 3: Completed , 4: Canceled, 100:cancel_failed
        $clm24_model = Mage::getModel("myidshipping/ordergrid")->getCollection()
	->addFieldToFilter('main_table.order_status_id', array('eq' => 100));

        $orderData = $clm24_model->getData();
        foreach ($orderData as $result) {
            $entityId = $result['entity_id'];
            $orderId = $result['order_id'];
            // $customerId       = $result['customer_id'];
            $realorderId = $result['real_order_id'];

            $_clm24Order = $_cml24Helper->cancelOrder($clm24_token, $orderId);
        }
        $_cml24Helper->closeMyIDSession($clm24_token);
    }

    /**
     * Get Order updates from Ship2MyId Server for pending orders with batch
     * 
     */    
    
    public function batchscheduler() {

        $_cml24Helper = Mage::Helper('myidshipping');
        $clm24_token = $_cml24Helper->getMyIDSession();
        // 0: Pending, 1: Accepted, 2: Rejected, 3: Completed
        $clm24_model = Mage::getModel("myidshipping/ordergrid")->getCollection()
	->addFieldToFilter('main_table.order_status_id', array('eq' => 0))
	->addFieldToFilter('main_table.real_order_id', array('neq' => ''));

        $orderData = $clm24_model->getData();

        $real_order_ids = array();
        $process_orders = array();
        if (count($orderData)) {
            foreach ($orderData as $k => $oData) {
	if ($oData['real_order_id'] != '') {
	    $real_order_ids[] = $oData['real_order_id']; //['order_id_list']
	    $process_orders[$oData['real_order_id']] = $oData;
	    unset($orderData[$k]);
	}
            }
        }
        $orderData = null;

        if (count($real_order_ids)) {
            $batch_real_order_ids = array_chunk($real_order_ids, 50);
        }

        if (count($batch_real_order_ids)) {
            foreach ($batch_real_order_ids as $order_ids) {
	if (count($order_ids)) {
	    $_orderStatus = $_cml24Helper->checkOrderStatus($clm24_token, array('order_id_list' => $order_ids), 'both');
	    if (!empty($_orderStatus) && is_array($_orderStatus)) {
	        if (isset($_orderStatus['accepted_order']) && is_array($_orderStatus['accepted_order']) && count($_orderStatus['accepted_order'])) {
	            foreach ($_orderStatus['accepted_order'] as $_oid) {
		if (isset($process_orders[$_oid])) {

		    $_accp_order = $process_orders[$_oid];
		    $entityId = $_accp_order['entity_id'];
		    $orderId = $_accp_order['order_id'];
		    $realorderId = $_accp_order['real_order_id'];
		    if (!empty($realorderId)) {
		        $_clm24Order = $_cml24Helper->checkOrder($clm24_token, $realorderId);
		        $tele = "NIL";
		        if (isset($_clm24Order["status"])) {
		            $_message = $_clm24Order["message"];
		            Mage::Log(__METHOD__ . ' :: ' . __LINE__ . " " . $_message);
		        } else {
		            $order = Mage::getModel('sales/order')
			    ->getCollection()
			    ->addAttributeToFilter('state', array('neq' => Mage_Sales_Model_Order::STATE_CANCELED))
			    ->addAttributeToFilter('increment_id', $orderId)
			    ->getFirstItem();
		            $_order_status_id = 0;
		            // Do we have any response
		            if ($order->getId()) {
			if ($_clm24Order["is_order_accepted"] == "true") {
			    $_order_status_id = 1;
			    $newOrderStatus = Mage::getStoreConfig('clm24core/shippings/order_status');
			    if ($newOrderStatus == 'processing') {
			        $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true)->save();
			    }
			    // We need to update the ship to Address too
			    $clm24_shippingmodel = Mage::getModel('sales/order_address')->load($order->getShippingAddressId());
			    $regionModel = Mage::getModel('directory/region')->loadByCode($_clm24Order["state"], $_clm24Order["countryCode"]);
			    $regionId = $regionModel->getId();

			    if ($_clm24Order["phoneNumber"])
			        $tele = $_clm24Order["phoneNumber"];
			    else
			        $tele = "NIL";

			    $_shipping = Array
			        (
			        //            [address_id] => 0
			        "entity_id" => $order->getShippingAddressId(),
			        "parent_id" => $clm24_shippingmodel->getData("parent_id"),
			        "firstname" => ($_clm24Order["receiver_first_name"] ? $_clm24Order["receiver_first_name"] : '' ),
			        "lastname" => ($_clm24Order["receiver_last_name"] ? $_clm24Order["receiver_last_name"] : ''),
			        "company" => "",
			        "street" => $_clm24Order["address_1"] . ' ' . $_clm24Order["address_2"],
			        "city" => $_clm24Order["city"],
			        "region_id" => $regionId,
			        "region" => $_clm24Order["stateName"],
			        "postcode" => $_clm24Order["zipcode"],
			        "country_id" => $_clm24Order["countryCode"],
			        "telephone" => $_clm24Order["receiver_telephone"],
			        "fax" => "",
			        "address_type" => "shipping"
			    );
			    if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
			        Mage::log('shipping array:' . $_shipping);
			    }
			    $clm24_shippingmodel->setData($_shipping)->save();

			    $resource = Mage::getSingleton('core/resource');
			    $writeConnection = $resource->getConnection('core_write');
			    $table = $resource->getTableName('myidshipping/shipping');

			    $queryfirst = "Update " . $table . " set value='" . $_clm24Order["receiver_first_name"] . "' where order_grid_id ='" . $entityId . "' and (attribute ='firstname' or attribute ='receiver_first_name')";
			    if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
			        Mage::log('update_first:' . $queryfirst);
			    }
			    $writeConnection->query($queryfirst);

			    $querylast = "Update " . $table . " set value='" . $_clm24Order["receiver_last_name"] . "' where order_grid_id ='" . $entityId . "' and (attribute ='lastname' or attribute ='receiver_last_name')";
			    if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
			        Mage::log('update_last:' . $querylast);
			    }
			    $writeConnection->query($querylast);

			    $query = "Update " . $table . " set value='" . $tele . "' where order_grid_id ='" . $entityId . "' and attribute ='r_telephone' ";
			    if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
			        Mage::log('update_telephone:' . $query);
			    }
			    $writeConnection->query($query);

			    // Save the data
			    $_data = array(
			        "entity_id" => $entityId,
			        "order_status_id" => $_order_status_id,
			        "r_name"=> $_clm24Order["receiver_first_name"].' '.$_clm24Order["receiver_last_name"], 
			        "r_telephone"=> $tele, 
			        "updated_at" => date('Y-m-d H:i:s')
			    );

			    $clm24_model = Mage::getModel("myidshipping/ordergrid")
			            ->setData($_data)
			            ->save();
			}
		            }else {
			Mage::log('order not found');
		            }
		            $order = null;
		        }
		        $_clm24Order = null;
		    }
		} else {
		    continue;
		}
	            }
	        }
	        if (isset($_orderStatus['rejected_order']) && is_array($_orderStatus['rejected_order']) && count($_orderStatus['rejected_order'])) {
	            foreach ($_orderStatus['rejected_order'] as $_oid) {
		if (isset($process_orders[$_oid['order_id']])) {
		    $_rej_order = $process_orders[$_oid['order_id']];
		    $entityId = $_rej_order['entity_id'];
		    $orderId = $_rej_order['order_id'];
		    $realorderId = $_rej_order['real_order_id'];
		    if (!empty($realorderId)) {
		        $_order_status_id = 2;
		        $order = Mage::getModel('sales/order')
			->getCollection()
			->addAttributeToFilter('state', array('neq' => Mage_Sales_Model_Order::STATE_CANCELED))
			->addAttributeToFilter('increment_id', $orderId)
			->getFirstItem();

		        if ($order->getId()) {
		            $rejectedOrderStatus = Mage::getStoreConfig('clm24core/shippings/rejected_order_status');
		            if ($rejectedOrderStatus == 'canceled') {
			$order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, $_clm24Order["receiver_rejected_note"])->save();
		            } else {
			$order->setState(Mage_Sales_Model_Order::STATE_HOLDED, true, $_clm24Order["receiver_rejected_note"])->save();
		            }

		            $_data = array(
			"entity_id" => $entityId,
			"order_status_id" => $_order_status_id,
			//"r_name"=> $_clm24Order["receiver_first_name"].' '.$_clm24Order["receiver_last_name"], 
			///"r_telephone"=> $tele, 
			"updated_at" => date('Y-m-d H:i:s')
		            );

		            $clm24_model = Mage::getModel("myidshipping/ordergrid")
			    ->setData($_data)
			    ->save();
		        }
		    }
		} else {
		    continue;
		}
	            }
	        }
	    }
	}
            }
        }
        $_cml24Helper->closeMyIDSession($clm24_token);
    }

}
