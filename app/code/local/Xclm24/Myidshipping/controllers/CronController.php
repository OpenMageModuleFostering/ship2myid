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
 * Cron controller for Myidshipping
 */

class Xclm24_Myidshipping_CronController extends Mage_Core_Controller_Front_Action 
{

    /**
     * This function is used to run CRON
     * to check the Ship2MyId Order Status
     *
     * @return 
     */    
    public function schedulerAction() 
    {

        $_cml24Helper = Mage::Helper('myidshipping');
        $clm24_token = $_cml24Helper->getMyIDSession();
        $order_status_id = $_REQUEST['order_status'];
        $username = $_REQUEST['username'];
        $password = $_REQUEST['password'];
        $order_details = $_REQUEST['order_details'];
        $access_token = $_REQUEST['access_token'];

        // Get username and password

        $merchantCode = Mage::getStoreConfig('clm24core/shippings/merchantID');

        $mpassword = md5(Mage::helper('core')->decrypt(Mage::getStoreConfig('clm24core/shippings/merchantPassword')));

        if ($merchantCode == $username && $password == $mpassword) {

            $url = Mage::getStoreConfig('clm24core/shippings/clm24Url') . "/session/?";
            $_requestUrl = $url . "access_token=" . $access_token;


            $ch = curl_init($_requestUrl);
            $headers = array("Content-Type: application/json");
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);

            if ($response) {
	$_objResponse = Zend_Json::decode($response);
	if (isset($_objResponse["Session"])) {
	    
	} else {

	    $_result = array("status" => $_objResponse["Error"]["status"], "message" => $_objResponse["Error"]["message"]);
	}
            }
        } else {
            $result['error'] = 1;
            $result['message'] = $this->__('username and password does not match');
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
        }

        if ($order_status_id == "0") {
            // 0: Pending, 1: Accepted, 2: Rejected, 3: Completed
            $clm24_model = Mage::getModel("myidshipping/ordergrid")->getCollection()
	    ->addFieldToFilter('main_table.order_status_id', array('eq' => 0));



            $orderData = $clm24_model->getData();
            foreach ($orderData as $result) {
	$entityId = $result['entity_id'];
	$orderId = $result['order_id'];
	$customerId = $result['customer_id'];
	$realorderId = $result['real_order_id'];

	if (!empty($realorderId)) {

	    $_clm24Order = $_cml24Helper->checkOrder($clm24_token, $realorderId);
	    if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
	        Mage::log('$realorderId: ' . implode(',', $_clm24Order));
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

		$clm24_shippingmodel->setData($_shipping)->save();
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
		"receiver_telephone" => $tele,
		"updated_at" => date('Y-m-d H:i:s')
	            );

	            $clm24_model = Mage::getModel("myidshipping/ordergrid")
		    ->setData($_data)
		    ->save();



	            $clm24_model_tel = Mage::getModel("myidshipping/shipping")->getCollection()->addFieldToFilter('main_table.entity_id', $clm24_model->getEntityId())->addFieldToFilter('main_table.attribute', array('eq' => 'telephone'));

	            $orderDatatel = $clm24_model_tel->getData();
	            foreach ($orderDatatel as $resulttel) {
		$entityIdtel = $result['entity_id'];


		$_data_tel = array(
		    "entity_id" => $entityIdtel,
		    "value" => $tele
		);
	            }
	            $clm24_model_tel = Mage::getModel("myidshipping/shipping")
		    ->setData($_data_tel)
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
        if ($order_status_id == "100") {
            $_cml24Helper = Mage::Helper('myidshipping');
            $clm24_token = $_cml24Helper->getMyIDSession();

            // 0: Pending, 1: Accepted, 2: Rejected, 3: Completed , 4: Canceled, 100:cancel_failed
            $clm24_model = Mage::getModel("myidshipping/ordergrid")->getCollection()
	    ->addFieldToFilter('main_table.order_status_id', array('eq' => 100));

            $orderData = $clm24_model->getData();

            foreach ($orderData as $result) {
	$entityId = $result['entity_id'];
	$orderId = $result['order_id'];
	$customerId = $result['customer_id'];
	$realorderId = $result['real_order_id'];

	$_clm24Order = $_cml24Helper->cancelOrder($clm24_token, $orderId);
            }
            $_cml24Helper->closeMyIDSession($clm24_token);
        }
    }

}
