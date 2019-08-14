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
 * Myidshipping Observer Model
 * 
 */

class Xclm24_Myidshipping_Model_Observer 
{

    /**
     * After Submiting Billing Data, This Method called 
     * to set the Shipping Details
     * 
     * @param   Varien_Event_Observer $observer
     * @return  Varien_Event_Observer $observer
     */ 
    
    public function billingPostDispatch($observer) 
    {
        if ($observer->getEvent()->getControllerAction()->getFullActionName() == "checkout_onepage_saveBilling") {
            $session = Mage::getSingleton('checkout/session');
            $sdata = $session->getData('ship2myid');
            $billingpostdata = $observer->getEvent()->getControllerAction()->getRequest()->getPost('billing', array());
            $is_ship2myid_order_flag = $observer->getEvent()->getControllerAction()->getRequest()->getPost('is_ship2myid_order_flag');

            if (isset($sdata) && $sdata != "NULL" && $is_ship2myid_order_flag == 1) {
	$session = Mage::getSingleton('checkout/session');
	$session->setData('use_mapmyid', 1);
	/* $checkout = Mage::getSingleton('checkout/session')->getQuote();
	  $billAddress = $checkout->getBillingAddress();
	  $billAddress->setData('use_for_shipping',1);
	  $billAddress->save(); */
	$controller = $observer->getControllerAction();
	$controller->setFlag('', Mage_Core_Controller_Varien_Action::FLAG_NO_DISPATCH, true);
	$_cml24Helper = Mage::Helper('myidshipping');
	$_cml24Helper->saveMyidshipping();
	$result['goto_section'] = 'shipping_method';
	$result['update_section'] = array(
	    'name' => 'shipping-method',
	    'html' => $this->_getShippingMethodsHtml()
	);
	$result['allow_sections'] = array('shipping');
	$result['duplicateBillingInfo'] = 'true';
	$controller->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
            } else {
	$session = Mage::getSingleton('checkout/session');
	$session->setData('use_mapmyid', 0);
	$session->unsShip2myid();
            }
            /* 		
              $checkout = Mage::getSingleton('checkout/session')->getQuote();
              $billAddress = $checkout->getShippingAddress();
              Zend_Debug::dump($billAddress->getData());
              exit;
             */
        }
        return $observer;
    }

    /**
     * Return the Shipping Method HTML
     * 
     * @return String 
     */ 
    
    public function _getShippingMethodsHtml() {
        $layout = Mage::app()->getLayout();
        $update = $layout->getUpdate();
        $update->load('checkout_onepage_shippingmethod');
        $layout->generateXml();
        $layout->generateBlocks();
        $output = $layout->getOutput();
        return $output;
    }

    //Ship2myid only rule saving to db

    /**
     * This Event call after applying Coupon code 
     * Only for Ship2MyId Coupon code
     * 
     * @param   Varien_Event_Observer $observer
     */ 
    
    public function setShip2myidOnlyCoupon(Varien_Event_Observer $observer) {
        $salesruleSaveRequest = $observer->getEvent()->getRequest();
        $salerulePostData = $observer->getEvent()->getData();
        $salerulePostData = $salerulePostData['data_object']->getdata();
        /* zend_debug::dump($salerulePostData['data_object']->getdata());
          exit; */
        $clm24myidCouponModel = Mage::getModel('myidshipping/coupon')->loadByRuleId($salerulePostData['rule_id']);
        if ($clm24myidCouponModel->getData()) {
            $clm24myidCouponModel->setShip2myidOnly($salerulePostData['ship2myid_only'])->save();
        } else {
            $clm24RuleData = array(
	"rule_id" => $salerulePostData['rule_id'],
	"ship2myid_only" => $salerulePostData['ship2myid_only']
            );
            //zend_debug::dump($clm24RuleData);
            //exit;
            $clm24myidCouponModel->setData($clm24RuleData)->save();
        }
    }

    //paypal fix - order status for ship2myid orders

    /**
     * After Order Save this event get called
     * 
     * @param   Varien_Event_Observer $observer
     */ 
    
    public function salesOrderSaveAfter(Varien_Event_Observer $observer) {
        $order = $observer->getEvent()->getOrder(); // get order data
        $clm24_model = Mage::getModel("myidshipping/ordergrid")->getCollection()
	->addFieldToFilter('main_table.order_id', array('eq' => $order->getIncrementId()));
        $orderData = $clm24_model->getData();
        $_code = $order->getPayment()->getMethodInstance()->getCode();
        if (count($orderData) > 0 && (stripos($_code, "paypal") !== false || stripos($_code, "payflow") !== false || stripos($_code, "verisign") !== false || stripos($_code, "paypaluk") !== false )) {
            $clm24_modelData = $orderData[0];
            $rejectedOrderStatus = Mage::getStoreConfig('clm24core/shippings/rejected_order_status');
            if ($rejectedOrderStatus != 'canceled') {
	$rejectedOrderStatus = 'holded';
            }
            if (($clm24_modelData['order_status_id'] == 2 || $clm24_modelData['order_status_id'] == 4 || $clm24_modelData['order_status_id'] == 100) && $order->getState() != $rejectedOrderStatus) {
	if ($rejectedOrderStatus == 'canceled') {
	    $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true)->save();
	} else {
	    $order->setState(Mage_Sales_Model_Order::STATE_HOLDED, true)->save();
	}
            }
            // Enforce Pending order status for Ship2MyID orders before accept/reject
            if ($clm24_modelData['order_status_id'] == 0 && $order->getState() == Mage_Sales_Model_Order::STATE_PROCESSING && Mage::getStoreConfig('clm24core/shippings/ship2myid_order_status')) {
	$order->setState(Mage_Sales_Model_Order::STATE_NEW, true)->save();
            }
        }
    }

    /**
     * Check Payment Methods
     * 
     * @param   Varien_Event_Observer $observer
     */ 
    
    public function paymentMethodCheck(Varien_Event_Observer $observer) {
        if (Mage::getStoreConfig('clm24core/shippings/enabled')) {

            $event = $observer->getEvent();
            $method = $event->getMethodInstance();
            $result = $event->getResult();
            $session = Mage::getSingleton('checkout/session');
            $_clm24Data = $session->getData('ship2myid');
            $use_mapmyid = $session->getData('use_mapmyid');
            if (!empty($_clm24Data) && isset($_clm24Data['email']) && $_clm24Data['email'] <> '' && $use_mapmyid) {
	if ($method->getCode() == 'paypal_express') {
	    $result->isAvailable = false;
	}
            }
        }
    }

    /**
     * After Order Place this event get called
     * 
     * @param   Varien_Event_Observer $observer
     */ 
    
    public function salesOrderPlaceAfter($observer) {
        if (Mage::getStoreConfig('clm24core/shippings/enabled')) {
            //get the event and pull the URL object and the storeId from the event.
            $_event = $observer->getEvent();
            $_urlObject = $_event->getUrlObject();
            $_storeId = $_event->getStoreId();

            $order = $observer->getOrder();
            // Get session data
            $session = Mage::getSingleton('checkout/session');
            $_clm24Data = $session->getData('ship2myid');
            Mage::getSingleton('core/session')->setShip2myid($_clm24Data);
            if (!empty($_clm24Data) && isset($_clm24Data['email']) && $_clm24Data['email'] <> '') {
	// Now we need to get the order_id, customer_id, store_id, clm24_token, real_order_id, order_status_id
	$_order_id = $order->getRealOrderId();
	$_customer_id = $order->getCustomerId();
	if (empty($_customer_id)) {
	    // We have guest
	    $_customer_id = NULL;
	}
	$_store_id = $order->getStoreId();
	// Set the Order Status ID
	// 0: Pending, 1: Accepted, 2: Rejected, 3: Completed 4:Canceled
	$_order_status_id = 0;
	// change status for ship2myid orders when used credit card
	if (Mage::getStoreConfig('clm24core/shippings/ship2myid_order_status')) {
	    $order->setState(Mage_Sales_Model_Order::STATE_NEW, true)->save();
	}
	// Now get the Clm24 Token and submit the order
	$_cml24Helper = Mage::Helper('myidshipping');
	$clm24_token = $_cml24Helper->getMyIDSession();
	$_clm24Order = $_cml24Helper->submitOrder($clm24_token, $order, $_clm24Data);
	//zend_debug::dump($_clm24Order);
	//zend_debug::dump($_clm24Data);
	//exit;
	$_cml24Helper->closeMyIDSession($clm24_token);
	//Zend_debug::Dump($_clm24Order);
	//exit;
	if (isset($_clm24Order["status"])) {
	    $_message = $_clm24Order["message"];
	    Mage::throwException($_message);
	} else {
	    // Do we have any response
	    if ($_clm24Order["is_order_accepted"] == "true") {
	        $_order_status_id = 1;
	        $newOrderStatus = Mage::getStoreConfig('clm24core/shippings/order_status');
	        if ($newOrderStatus == 'processing') {
	            $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true)->save();
	        }
	        // We need to update the ship to Address too
	        // "receiver_first_name":"John ","receiver_last_name":"Lee","address_1":"2367 Magnolia Bridge Dr","city":"San Ramon","state":"CA","country":"US","zipcode":"94582"
	        // Update the shipping address
	        $clm24_shippingmodel = Mage::getModel('sales/order_address')->load($order->getShippingAddressId());
	        $regionModel = Mage::getModel('directory/region')->loadByCode($_clm24Order["state"], $_clm24Order["countryCode"]);
	        $regionId = $regionModel->getId();
	        if ($_clm24Order["phoneNumber"])
	            $tele = $_clm24Order["phoneNumber"];
	        else
	            $tele = "NIL";
	        $_confir_ship2 = Mage::getStoreConfig('clm24core/shippings/ship2myid_label');
	        $_shipping = Array
	            (
	            //            [address_id] => 0
	            "entity_id" => $order->getShippingAddressId(),
	            "parent_id" => $clm24_shippingmodel->getData("parent_id"),
	            "firstname" => $_clm24Order["receiver_first_name"],
	            "lastname" => $_clm24Order["receiver_last_name"],
	            "company" => "",
	            "street" => $_clm24Order["address_1"] . " " . $_clm24Order["address_2"],
	            "city" => $_clm24Order["city"],
	            "region_id" => $regionId,
	            "region" => $_clm24Order["stateName"],
	            "postcode" => $_clm24Order["zipcode"],
	            "country_id" => $_clm24Order["countryCode"],
	            "telephone" => $_clm24Data["telephone"],
	            "fax" => "",
	            "address_type" => "shipping"
	        );
	        $clm24_shippingmodel->setData($_shipping)->save();
	        //set Max shipping flag for guest users
	        // Get session data
	        $session = Mage::getSingleton('checkout/session');
	        $_clm24Data_max = $session->getData('ship2myid_maxship');
	        //Zend_Debug::Dump($_clm24Data_max);
	        //exit;
	        $maindata = array();
	        if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
	            Mage::Log("Clm24 [Observer_max_shipping]: " . print_r($_clm24Data_max, true));
	        }
	        if ($_clm24Data_max['max_shipping']) {
	            $_datagrid = array(
		"order_id" => $_order_id,
		"real_order_id" => $_clm24Order["id"],
		"max_shipment" => 1,
		"order_group_id" => $_clm24Order["external_order_group_id"],
		"order_status_id" => $_order_status_id,
		"created_at" => date('Y-m-d H:i:s'),
		"updated_at" => date('Y-m-d H:i:s')
	            );
	        } else {
	            $_datagrid = array(
		"order_id" => $_order_id,
		"real_order_id" => $_clm24Order["id"],
		"max_shipment" => 0,
		"order_group_id" => $_clm24Order["external_order_group_id"],
		"order_status_id" => $_order_status_id,
		"created_at" => date('Y-m-d H:i:s'),
		"updated_at" => date('Y-m-d H:i:s')
	            );
	        }
	        $clm24_model_grid = Mage::getModel("myidshipping/ordergrid")->setData($_datagrid)
		->save();
	        foreach ($_clm24Order as $key => $value) {
	            $_data = array(
		"order_grid_id" => $clm24_model_grid->getEntityId(),
		"attribute" => $key,
		"value" => $value,
	            );
	            array_push($maindata, $_data);
	        }
	        $_data = array(
	            "order_grid_id" => $clm24_model_grid->getEntityId(),
	            "attribute" => 'r_telephone',
	            "value" => $tele,
	        );
	        array_push($maindata, $_data);
	        $clm24_model = Mage::getModel("myidshipping/shipping");
	        foreach ($maindata as $data) {
	            $clm24_model->setData($data)
		    ->save();
	        }
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
	        $_cml24Helper->cancelOrder($clm24_token, $order->getIncrementId());
	    }
	    if ($_clm24Order["is_order_accepted"] == "false") {
	        //our popup data saved to table
	        //set Max shipping flag for guest users
	        // Get session data
	        $session = Mage::getSingleton('checkout/session');
	        $_clm24Data_max = $session->getData('ship2myid_maxship');
	        //Zend_Debug::Dump($_clm24Data_max);
	        //exit;
	        if ($_clm24Order["phoneNumber"])
	            $tele = $_clm24Order["phoneNumber"];
	        else
	            $tele = "NIL";
			
	        $maindata = array();
	        if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
	            Mage::Log("Clm24 [Observer_max_shipping]: " . print_r($_clm24Data_max, true));
	        }
	        if ($_clm24Data_max['max_shipping']) {
	            $_datagrid = array(
		"order_id" => $_order_id,
		"real_order_id" => $_clm24Order["id"],
		"max_shipment" => 1,
		"order_group_id" => $_clm24Order["external_order_group_id"],
		"order_status_id" => $_order_status_id,
		"created_at" => date('Y-m-d H:i:s'),
		"updated_at" => date('Y-m-d H:i:s')
	            );
	        } else {
	            $_datagrid = array(
		"order_id" => $_order_id,
		"real_order_id" => $_clm24Order["id"],
		"max_shipment" => 0,
		"order_group_id" => $_clm24Order["external_order_group_id"],
		"order_status_id" => $_order_status_id,
		"created_at" => date('Y-m-d H:i:s'),
		"updated_at" => date('Y-m-d H:i:s')
	            );
	        }
	        $clm24_model_grid = Mage::getModel("myidshipping/ordergrid")->setData($_datagrid)
		->save();
	        foreach ($_clm24Data as $key => $value) {
	            $_data = array(
		"order_grid_id" => $clm24_model_grid->getEntityId(),
		"attribute" => $key,
		"value" => $value,
	            );

	            array_push($maindata, $_data);
	        }
	        $_data = array(
	            "order_grid_id" => $clm24_model_grid->getEntityId(),
	            "attribute" => 'r_telephone',
	            "value" => $tele,
	        );
	        array_push($maindata, $_data);
	        $clm24_model = Mage::getModel("myidshipping/shipping");
	        foreach ($maindata as $data) {
	            $clm24_model->setData($data)
		    ->save();
	        }
	    }
	}
	// Reset Sessions
	$session->setData('ship2myid', null);
	$session->setData('ship2myid_maxship', null);
            }
        }
    }

    /**
     * Change order grid collection for ship2myID status
     * 
     * @param   Varien_Event_Observer $observer
     */ 
    

    public function salesOrderGridCollectionLoadBefore($observer) {
        if (Mage::getStoreConfig('clm24core/shippings/enabled')) {
            $collection = $observer->getOrderGridCollection();
            $select = $collection->getSelect();
            $clm24Table = Mage::getSingleton('core/resource')->getTableName('clm24_myidshipping_ordergrid');
            $clm24MyShipping = Mage::getSingleton('core/resource')->getTableName('clm24_myidshipping');

            $select->joinLeft($clm24Table, 'main_table.increment_id = ' . $clm24Table . '.order_id', array('order_group_id', 'order_status_id', 'max_shipment'));
            $select->joinLeft(array('clm1' => $clm24MyShipping), $clm24Table . '.entity_id = clm1.order_grid_id AND clm1.attribute = "email" ', array('clm1.value as rec_email'));
            //$select->joinLeft(array('clm2' => $clm24MyShipping), $clm24Table . '.entity_id = clm2.order_grid_id AND clm2.attribute = "firstname" ', array('clm2.value as rec_firstname'));
            //$select->joinLeft(array('clm3' => $clm24MyShipping), $clm24Table . '.entity_id = clm3.order_grid_id AND clm3.attribute = "lastname" ', array( ' CONCAT_WS(" ", clm2.`value` , clm3.`value`) as shipping_name' ));

            if ($where = $select->getPart('where')) {
	foreach ($where as $key => $condition) {
	    $new_condition = $condition;
	    if (strpos($condition, 'created_at')) {
	        $new_condition = str_replace("created_at", "main_table.created_at", $condition);
	        $where[$key] = $new_condition;
	    }
	    if (strpos($new_condition, 'store_id')) {
	        $new_condition = str_replace("store_id", "main_table.store_id", $new_condition);
	        $where[$key] = $new_condition;
	    }

	    /* if (strpos($new_condition, 'shipping_name')) {
	      $new_condition = str_replace("shipping_name", " CONCAT_WS( ' ',  clm2.`value` , clm3.`value` )", $new_condition);
	      $where[$key] = $new_condition;
	      } */
	}
	$select->setPart('where', $where);
            }

            //die;
        }
    }

    /**
     * Add column to sales orders grid
     * 
     * @param   Varien_Event_Observer $observer
     * @return  Varien_Event_Observer $observer
     */ 
    

    public function addColumn($observer) {
        if (Mage::getStoreConfig('clm24core/shippings/enabled')) {
            $block = $observer->getEvent()->getBlock();
            $clm24Table = Mage::getSingleton('core/resource')->getTableName('clm24_myidshipping_ordergrid');
            $clm24MyShipping = Mage::getSingleton('core/resource')->getTableName('clm24_myidshipping');
            if (strpos((get_class($block)), 'Sales_Order_Grid')) {
	$block->removeColumn('created_at');
	$block->addColumnAfter('created_at', array(
	    'header' => Mage::helper('sales')->__('Purchased On'),
	    'index' => 'created_at',
	    'type' => 'datetime',
	    'filter_index' => 'main_table.created_at',
	    'width' => '100px',
	        ), 'store_id');
	$block->addColumnAfter('value', array(
	    'header' => Mage::helper('sales')->__('Rec. Email '),
	    'index' => 'rec_email',
	    'width' => '150px',
	    'filter_index' => 'clm1.value',
	        ), 'shipping_name');

	$block->addColumnAfter('order_status_id', array(
	    'header' => Mage::helper('sales')->__('Ship2MyID Status'),
	    'index' => 'order_status_id',
	    'width' => '70px',
	    'type' => 'options',
	    'filter_index' => $clm24Table . '.order_status_id',
	    // '0: Pending, 1: Accepted, 2: Rejected, 3: Completed,  4: Canceled', 100:Need to cancel at ship2myid
	    'options' => array('' => 'Not Applicable', '0' => 'Pending', '1' => 'Accepted', '2' => 'Rejected', '3' => 'Completed', '4' => 'Canceled', '100' => 'Cancel_Failed'),
	        ), 'status');
	$block->addColumnAfter('max_shipment', array(
	    'header' => Mage::helper('sales')->__('Ship2MyID Max Shipment'),
	    'index' => 'max_shipment',
	    'width' => '70px',
	    'type' => 'options',
	    'filter_index' => $clm24Table . '.max_shipment',
	    // '0: Pending, 1: Accepted, 2: Rejected, 3: Completed,  4: Canceled', 100:Need to cancel at ship2myid
	    'options' => array('' => 'Not Applicable', '0' => 'No', '1' => 'Yes'),
	        ), 'order_status_id');

	$block->addColumnAfter('order_group_id', array(
	    'header' => Mage::helper('sales')->__('Ship2MyID Group #'),
	    'index' => 'order_group_id',
	    'type' => 'text',
	    'filter_index' => $clm24Table . '.order_group_id',
	    'width' => '80px',
	        ), 'real_order_id');
            }
        }
    }

}