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
 * Myidshipping default helper
 */

class Xclm24_Myidshipping_Helper_Data extends Mage_Core_Helper_Abstract 
{

    /**
     * Close the Session with Ship2MyId Server
     * @param   Strinng $_clm24Session
     * 
     */      
    public function closeMyIDSession($_clm24Session) 
    {
        $url = Mage::getStoreConfig('clm24core/shippings/clm24Url') . "/session?";
        $_requestUrl = $url . "access_token=" . $_clm24Session;
        $ch = curl_init($_requestUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
            Mage::Log("Clm24: " . print_r($response, true));
        }
        curl_close($ch);
    }
    
    /**
     * Submit Order at Ship2MyId Server
     * 
     * @param   String $clm24_token
     * @param   Mage_Sales_Model_Order $order
     * @param   Array $_clm24Data
     * 
     * @return result array()
     */      

    public function submitOrder($clm24_token, $order, $_clm24Data) 
    {
        if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
            Mage::Log("Submit Order: " . print_r($_clm24Data, true));
        }
        $_result = null;
        $url = Mage::getStoreConfig('clm24core/shippings/clm24Url') . "/order/create?";
        $_requestUrl = $url . "access_token=" . $clm24_token;
        $xml = new DOMDocument('1.0', 'utf-8');
        $xmlRoot = $xml->createElement('OrderDetails');
        $xml->appendChild($xmlRoot);
        $orderItems = $order->getItemsCollection(array(), true);
        foreach ($orderItems as $item) {
            $item_id = $item->getData("item_id");
            $order_id = $item->getData("order_id");
            $product_id = $item->getData("product_id");
            $product_sku = $item->getSku();
            $product_name = $item->getName();
            $qty = $item->getData("qty_ordered");
            $price = $item->getData("price");
            $subtotal = $item->getData("row_total");
            $taxtotal = $item->getData("tax_amount");
            $grandtotal = $item->getData("row_total_incl_tax");
            $xmlItem = $xml->createElement('Item');
            $xmlItem->appendChild($xml->createTextNode($product_name));
            $domAttribute = $xml->createAttribute('MerchentOrderRecordRef');
            $domAttribute->value = $order_id;
            $xmlItem->appendChild($domAttribute);
            $domAttribute = $xml->createAttribute('MerchentOrderRecordLineRef');
            $domAttribute->value = $item_id;
            $xmlItem->appendChild($domAttribute);
            $domAttribute = $xml->createAttribute('Sku');
            $domAttribute->value = $product_sku;
            $xmlItem->appendChild($domAttribute);
            $domAttribute = $xml->createAttribute('Qty');
            $domAttribute->value = $qty;
            $xmlItem->appendChild($domAttribute);
            $domAttribute = $xml->createAttribute('Price');
            $domAttribute->value = $price;
            $xmlItem->appendChild($domAttribute);
            $domAttribute = $xml->createAttribute('LineSubTotal');
            $domAttribute->value = $subtotal;
            $xmlItem->appendChild($domAttribute);
            $domAttribute = $xml->createAttribute('LineTaxesTotal');
            $domAttribute->value = $taxtotal;
            $xmlItem->appendChild($domAttribute);
            $domAttribute = $xml->createAttribute('LineTotal');
            $domAttribute->value = $grandtotal;
            $xmlItem->appendChild($domAttribute);
            $xmlRoot->appendChild($xmlItem);
        }
        $xmlOrderDetails = $xml->saveXML();
        $_orderDetailsXML = $arrOrderInfo = array(
            "ExternalOrder" => array(
	"sender_email_address" => $order->getData("customer_email"),
	"sender_first_name" => $order->getData("customer_firstname"),
	"sender_last_name" => $order->getData("customer_lastname"),
	"sender_message" => "Brought to you by Ship2MyID",
	"receiver_email_address" => $_clm24Data["email"],
	"receiver_first_name" => $_clm24Data["firstname"],
	"receiver_last_name" => $_clm24Data["lastname"],
	"receiver_telephone" => $_clm24Data["telephone"],
	"receiver_type" => $_clm24Data["receiver_type"],
	"receiver_linkedin_id" => $_clm24Data["receiver_linkedin_id"],
	"receiver_facebook_id" => $_clm24Data["receiver_facebook_id"],
	"marketplace_order_data" => $xmlOrderDetails
            )
        );
        if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
            Mage::Log("Clm24 [submitOrder Array]: " . print_r($arrOrderInfo, true));
            Mage::Log("Clm24 [submitOrder Payload Data]: " . print_r(Zend_Json::Encode($arrOrderInfo), true));
        }
        $ch = curl_init($_requestUrl);
        $headers = array(
            "Content-Type: application/json"
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, Zend_Json::Encode($arrOrderInfo));
        $response = curl_exec($ch);
        if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
            Mage::Log("Clm24 [submitOrder Response ]: " . print_r($response, true));
        }
        if ($response) {
            $_objResponse = Zend_Json::decode($response);
            if (isset($_objResponse["ExternalOrder"])) {
	$_result = $_objResponse["ExternalOrder"];
            } else {
	$_result = array(
	    "status" => $_objResponse["Error"]["status"],
	    "message" => isset($_objResponse["Error"]["message"])?$_objResponse["Error"]["message"]:"Error By Server for ".__FUNCTION__
	);
            }
        }
        curl_close($ch);
        return $_result;
    }

    /**
     * Mark Order as Cancel Ship2MyId
     * 
     * @param   String $clm24_token
     * @param   Int $orderId
     * 
     * @return result array()
     */      
    
    public function cancelOrder($clm24_token, $orderId) 
    {
        $clm24_model = Mage::getModel("myidshipping/ordergrid")->loadByOrderId($orderId);
        $_result = null;
        $realorderId = $clm24_model->getData("real_order_id");
        $entityId = $clm24_model->getData("entity_id");
        if ((isset($clm24_model)) && (!empty($realorderId))) {
            $url = Mage::getStoreConfig('clm24core/shippings/clm24Url') . "/order/cancel/" . $realorderId . "?";
            $_requestUrl = $url . "access_token=" . $clm24_token;
            if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
	Mage::Log("Clm24 [cancelOrder]: " . print_r($realorderId, true));
	Mage::Log("Clm24 [cancelOrder]: " . print_r($url, true));
            }
            $ch = curl_init($_requestUrl);
            $headers = array(
	"Content-Type: application/json"
            );
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
	Mage::Log("Clm24 [cancelOrder]: " . print_r($response, true));
            }
            if ($response) {
	$_objResponse = Zend_Json::decode($response);
	if (isset($_objResponse["ExternalOrder"])) {
	    $_result = $_objResponse["ExternalOrder"];
	    if ($_result["is_order_cancelled"] == "true") {
	        $_data = array(
	            "entity_id" => $entityId,
	            "order_status_id" => 4,
	            "updated_at" => date('Y-m-d H:i:s')
	        );
	    } else {
	        $_data = array(
	            "entity_id" => $entityId,
	            "order_status_id" => 100,
	            "updated_at" => date('Y-m-d H:i:s')
	        );
	    }
	    $clm24_model = Mage::getModel("myidshipping/ordergrid")->setData($_data)->save();
	} else {
	    $_result = array(
	        "status" => $_objResponse["Error"]["status"],
	        "message" => isset($_objResponse["Error"]["message"])?$_objResponse["Error"]["message"]:"Error By Server for ".__FUNCTION__
	    );
	    $_data = array(
	        "entity_id" => $entityId,
	        "order_status_id" => 100,
	        "updated_at" => date('Y-m-d H:i:s')
	    );
	    $clm24_model = Mage::getModel("myidshipping/ordergrid")->setData($_data)->save();
	}
            }
            curl_close($ch);
        }
        return $_result;
    }

    /**
     * Mark Order as Completed at Ship2MyId
     * 
     * @param   String $clm24_token
     * @param   Int $orderId
     * 
     * @return result array()
     */      
    
    public function completeOrder($clm24_token, $orderId) 
    {
        $clm24_model = Mage::getModel("myidshipping/ordergrid")->loadByOrderId($orderId);
        $_result = null;
        $realorderId = $clm24_model->getData("real_order_id");
        $entityId = $clm24_model->getData("entity_id");
        if ((isset($clm24_model)) && (!empty($realorderId))) {
            $url = Mage::getStoreConfig('clm24core/shippings/clm24Url') . "/order/complete/" . $realorderId . "?";
            $_requestUrl = $url . "access_token=" . $clm24_token;
            if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
	Mage::Log("Clm24 [completeOrder]: " . print_r($realorderId, true));
            }
            $ch = curl_init($_requestUrl);
            $headers = array(
	"Content-Type: application/json"
            );
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
	Mage::Log("Clm24 [completeOrder]: " . print_r($response, true));
            }
            if ($response) {
	$_objResponse = Zend_Json::decode($response);
	if (isset($_objResponse["ExternalOrder"])) {
	    $_result = $_objResponse["ExternalOrder"];
	} else {
	    $_result = array(
	        "status" => $_objResponse["Error"]["status"],
	        "message" => isset($_objResponse["Error"]["message"])?$_objResponse["Error"]["message"]:"Error By Server for ".__FUNCTION__
	    );
	}
            }
            curl_close($ch);
            $_data = array(
	"entity_id" => $entityId,
	"order_status_id" => 3,
	"updated_at" => date('Y-m-d H:i:s')
            );
            $clm24_model = Mage::getModel("myidshipping/ordergrid")->setData($_data)->save();
        }
        return $_result;
    }

    /**
     * Check Order at Ship2MyId
     * 
     * @param   String $clm24_token
     * @param   Int $realorderId  
     * 
     * @return result array()
     */      
    
    public function checkOrder($clm24_token, $realorderId) 
    {
        $_result = null;
        $url = Mage::getStoreConfig('clm24core/shippings/clm24Url') . "/order/" . $realorderId . "?";
        $_requestUrl = $url . "access_token=" . $clm24_token;
        if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
            Mage::Log("Clm24 [checkOrder]: " . print_r($realorderId, true));
        }
        $ch = curl_init($_requestUrl);
        $headers = array(
            "Content-Type: application/json"
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
            Mage::Log("Clm24 [checkOrder]: " . print_r($response, true));
        }
        if ($response) {
            $_objResponse = Zend_Json::decode($response);
            if (isset($_objResponse["ExternalOrder"])) {
	$_result = $_objResponse["ExternalOrder"];
            } else {
	$_result = array(
	    "status" => $_objResponse["Error"]["status"],
	    "message" => isset($_objResponse["Error"]["message"])?$_objResponse["Error"]["message"]:"Error By Server for ".__FUNCTION__
	);
            }
        }
        curl_close($ch);
        return $_result;
    }
    
    /**
     * Check Order Status at Ship2MyId with Multiple OrderIds 
     * 
     * @param   String $clm24_token
     * @param   Array $orderIds
     * @param   String $status_type (both|)
     * 
     * @return result array()
     */      
    

    public function checkOrderStatus($clm24_token, $orderIds, $status_type = 'both') 
    {

        $_result = null;
        $url = Mage::getStoreConfig('clm24core/shippings/clm24Url') . "/order/status?";

        // Form the Url Request
        $url = $url . "access_token=" . $clm24_token . "&";
        $_requestUrl = $url . "status_type=" . $status_type;

        $ch = curl_init($_requestUrl);
        $headers = array("Content-Type: application/json");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, Zend_Json::Encode($orderIds));

        $response = curl_exec($ch);

        if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
            Mage::Log("Clm24 [submitOrder]: " . print_r($response, true));
        }
        if ($response) {
            $_result = Zend_Json::decode($response);
        } else {
            $_result = array('accepted_order' => array(), 'rejected_order' => array());
        }
        curl_close($ch);
        return $_result;
    }

    /**
     * Validae Token and return Access token for Merchant
     * 
     * @return String $_access_token
     */      
    
    public function getMyIDSession() 
    {

        $_access_token = '';
        $session = Mage::getSingleton('core/session');
        $_session_token = $session->getShip2MyIdAccesstoken();

        if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
            Mage::Log("Clm24 [Session Token]: " . print_r($_session_token, true));
        }

        if (isset($_session_token) && !empty($_session_token)) {
            $_validate_flag = $this->validateShip2MyIdToken($_session_token);
            if ($_validate_flag) {
	$_access_token = $_session_token;
            } else {
	$_access_token = $this->_getShip2MyIDSession();
            }
        } else {
            $_access_token = $this->_getShip2MyIDSession();
        }
        $session->setShip2MyIdAccesstoken($_access_token);
        return $_access_token;
    }

    /**
     * Validate Access Token from Ship2MyId Server
     * 
     * @param   String $clm24_token
     * @return String $_access_token
     */      
    
    public function validateShip2MyIdToken($clm24_token) 
    {

        if (!empty($clm24_token)) {
            $url = Mage::getStoreConfig('clm24core/shippings/clm24Url') . "/session/?";

            $_requestUrl = $url . "access_token=" . $clm24_token;

            if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
	Mage::Log(" Toekn Validation :  ");
	Mage::Log("Clm24 [validate Token]: " . print_r($_requestUrl, true));
            }

            $ch = curl_init($_requestUrl);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            //curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($userInfo));

            $response = curl_exec($ch);

            if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
	Mage::Log("Clm24 [getMyIDSession]: " . print_r($response, true));
            }
            curl_close($ch);
            if ($response) {
	$_objResponse = Zend_Json::decode($response);
	if (isset($_objResponse["Session"]["access_token"]) && !empty($_objResponse["Session"]["access_token"])) {
	    return true;
	} else {
	    return false;
	}
            } else {
	return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Get Access Token From Ship2Myd Server For Merchant
     * 
     * @param   String $clm24_token
     * @return  Array $_session
     */      

    public function _getShip2MyIDSession() 
    {
        $_session = null;
        $url = Mage::getStoreConfig('clm24core/shippings/clm24Url') . "/session/signin";

        // Get username and password
        $merchantCode = Mage::getStoreConfig('clm24core/shippings/merchantID');
        $password = Mage::helper('core')->decrypt(Mage::getStoreConfig('clm24core/shippings/merchantPassword'));

        // User Information
        $userInfo = array(
            "username" => base64_encode($merchantCode),
            "password" => base64_encode($password)
        );

        $jsondata = Zend_Json::encode($userInfo);
        $payload['signin_details'] = base64_encode($jsondata);
        $jsonpayload = Zend_Json::encode($payload);
        $_requestUrl = $url;     //. "username=" . $userInfo["username"] . "&password=" . $userInfo["password"];

        if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
            Mage::Log(" Login Step For Vendor :  ");
            Mage::Log("Clm24 [requestIrl]: " . print_r($_requestUrl, true));
            Mage::Log("Clm24 [payload]: " . print_r($jsonpayload, true));
        }

        $ch = curl_init($_requestUrl);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonpayload);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonpayload))
        );
        //curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($userInfo));

        $response = curl_exec($ch);

        if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
            Mage::Log("Clm24 [getMyIDSession]: " . print_r($response, true));
        }

        if ($response) {
            $_objResponse = Zend_Json::decode($response);
            $_session = $_objResponse["Session"]["access_token"];
        }

        // Close handle
        curl_close($ch);

        return $_session;
    }

    /* public function getMyIDSession() {
      $_session = null;
      $url = Mage::getStoreConfig('clm24core/shippings/clm24Url') . "/session/signin?";
      $merchantCode = Mage::getStoreConfig('clm24core/shippings/merchantID');
      $password = Mage::helper('core')->decrypt(Mage::getStoreConfig('clm24core/shippings/merchantPassword'));
      $userInfo = array(
      "username" => $merchantCode,
      "password" => $password
      );
      $_requestUrl = $url . "username=" . $userInfo["username"] . "&password=" . $userInfo["password"];

      $ch = curl_init($_requestUrl);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      $response = curl_exec($ch);
      if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
      Mage::Log("Clm24 [getMyIDSession URL]: " . print_r($_requestUrl, true));
      Mage::Log("Clm24 [getMyIDSession]: " . print_r($response, true));
      }

      if ($response) {
      $_objResponse = Zend_Json::decode($response);
      $_session = $_objResponse["Session"]["access_token"];
      }
      curl_close($ch);
      return $_session;
      } */

    /**
     * Get Access Token From Ship2Myd Server For User
     * 
     * @param   String $username
     * @param   String $password
     * @return  Array $_session
     */      

    public function getMyIDUserSession($username, $password) 
    {
        $_session = null;
        $url = Mage::getStoreConfig('clm24core/shippings/clm24Url') . "/session/signin";

        // Form the Url Request
        //$_requestUrl = $url . "username=" . $username . "&password=" . $password;

        $userInfo = array(
            "username" => base64_encode($username),
            "password" => base64_encode($password)
        );

        $jsondata = Zend_Json::encode($userInfo);
        $payload['signin_details'] = base64_encode($jsondata);
        $jsonpayload = Zend_Json::encode($payload);
        $_requestUrl = $url;

        if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
            Mage::Log(" Login Step :  ");
            Mage::Log("Clm24 [ requestUrl]: " . print_r($_requestUrl, true));
            Mage::Log("Clm24 [payload]: " . print_r($jsonpayload, true));
        }


        //Mage::log($_requestUrl);
        $ch = curl_init($_requestUrl);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonpayload);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonpayload))
        );


        $response = curl_exec($ch);

        if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
            Mage::Log("Clm24 [getMyIDUserSession]: " . print_r($response, true));
        }

        if ($response) {
            $_objResponse = Zend_Json::decode($response);
            $checkoutsession = Mage::getSingleton('checkout/session');
            $checkoutsession->unsetData('clm24contacts');
            $checkoutsession->unsetData('clm24recemails');
            $checkoutsession->unsetData('clm24Token');
            if (isset($_objResponse['Error']) && $_objResponse['Error']['status'] == 401) {
	$checkoutsession->unsetData('clm24Token');
	return array('error' => 1, 'message' => $_objResponse['Error']['message']);
            } else {
	$_userToken = $_objResponse["Session"]["access_token"];
	$checkoutsession->setData('clm24Token', $_userToken);
            }
        }

        // Close handle
        curl_close($ch);

        return $_userToken;
    }

    /* public function getMyIDUserSession($username, $password) {
      $_session = null;
      $url = Mage::getStoreConfig('clm24core/shippings/clm24Url') . "/session/signin?";
      $_requestUrl = $url . "username=" . $username . "&password=" . $password;
      $ch = curl_init($_requestUrl);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      $response = curl_exec($ch);
      if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
      Mage::Log("Clm24 [getMyIDUserSession]: " . print_r($response, true));
      }
      if ($response) {
      $_objResponse = Zend_Json::decode($response);
      $checkoutsession = Mage::getSingleton('checkout/session');
      $checkoutsession->unsetData('clm24contacts');
      $checkoutsession->unsetData('clm24recemails');
      $checkoutsession->unsetData('clm24Token');
      if (isset($_objResponse['Error']) && $_objResponse['Error']['status'] == 401) {
      $checkoutsession->unsetData('clm24Token');
      return array(
      'error' => 1,
      'message' => $_objResponse['Error']['message']
      );
      } else {
      $_userToken = $_objResponse["Session"]["access_token"];
      $checkoutsession->setData('clm24Token', $_userToken);
      }
      }
      curl_close($ch);
      return $_userToken;
      } */

    /**
     * Zip code detials for perticuar sender and receiver
     * 
     * @param   String $_clm24Session (Access Token)
     * @param   String $_sender_email_address
     * @param   String $_receiver_email_address
     * 
     * @return  Array $_zipcode
     */      
    
    public function getMyIDShippingSafeRecipientZipcode($_clm24Session, $_sender_email_address, $_receiver_email_address) 
    {
        $_zipcode = null;
        $url = Mage::getStoreConfig('clm24core/shippings/clm24Url') . "/order/safe_recipient_zipcode?";
        $_requestUrl = $url . "access_token=" . $_clm24Session . "&sender_email_address=" . $_sender_email_address . "&receiver_email_address=" . $_receiver_email_address;
        $ch = curl_init($_requestUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
            Mage::Log("Clm24 [getMyIDShippingSafeRecipientZipcode]: " . print_r($response, true));
        }
        if ($response) {
            $_objResponse = Zend_Json::decode($response);
            if (isset($_objResponse["Address"])) {
	$_zipcode = $_objResponse["Address"]["zipcode"];
            } else {
	$_zipcode = array(
	    "status" => $_objResponse["Error"]["status"],
	    "message" => isset($_objResponse["Error"]["message"])?$_objResponse["Error"]["message"]:"Error By Server for ".__FUNCTION__
	);
            }
        }
        curl_close($ch);
        return $_zipcode;
    }

    /**
     * Recipient Address detials for receiver
     * 
     * @param   String $_clm24Session (Access Token)
     * @param   String $_sender_email_address
     * @param   String $_receiver_email_address
     * @param   String $receiver_type ( email | facebook | linkedin )
     * @param   String $receiver_linkedin_id
     * @param   String $receiver_facebook_id
     * 
     * @return  Array $_zipcode
     */          
    
    public function getMyIDShippingSafeRecipientAddress($_clm24Session, $_sender_email_address, $_receiver_email_address , $receiver_type = 'email',$receiver_linkedin_id = '',$receiver_facebook_id = '') 
    {
        $_zipcode = null;
        $url = Mage::getStoreConfig('clm24core/shippings/clm24Url') . "/order/zipcode_state?";
        $_requestUrl = $url . "access_token=" . $_clm24Session. "&receiver_type=" . $receiver_type;	
        
        if($receiver_type == 'email'){
            $_requestUrl .= "&receiver_email_address=".$_receiver_email_address;
        }elseif($receiver_type == 'facebook'){
            $_requestUrl .= "&receiver_facebook_id=".$receiver_facebook_id;
        }elseif($receiver_type == 'linkedin'){
            $_requestUrl .= "&receiver_linkedin_id=".$receiver_linkedin_id;
        }else{
            $_requestUrl .= "&receiver_email_address=".$_receiver_email_address;
        }
        
        $ch = curl_init($_requestUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
            Mage::Log("Clm24 [getMyIDShippingSafeRecipientZipcode URL]: " . print_r($_requestUrl, true));
            Mage::Log("Clm24 [getMyIDShippingSafeRecipientZipcode]: " . print_r($response, true));
        }
        if ($response) {
            $_objResponse = Zend_Json::decode($response);
            if (isset($_objResponse["Address"])) {
	$_zipcode = array(
	    "zipcode" => $_objResponse["Address"]["zipcode"],
	    "state" => $_objResponse["Address"]["state_code"],
	    "country" => $_objResponse["Address"]["country_code"]
	);
            } else {
	$_zipcode = array(
	    "status" => $_objResponse["Error"]["status"],
	    "message" => isset($_objResponse["Error"]["message"])?$_objResponse["Error"]["message"]:"Error By Server for ".__FUNCTION__
	);
            }
        }
        curl_close($ch);
        return $_zipcode;
    }

    /**
     * Return YouTube Video Link 
     * 
     * @return  String
     */          
    
    public function getYtVideoUrl() 
    {
        return 'http://www.youtube.com/v/' . Mage::getStoreConfig('clm24core/shippings/ytvideoid') . '?autoplay=1';
    }

    /**
     * Check PayPal Payment method
     * 
     * @return  Boolean
     */          
    
    public function showPayPalNotice() {
        $payments = Mage::getSingleton('payment/config')->getActiveMethods();
        $methods = array();
        foreach ($payments as $_code => $paymentModel) {
            $methods[] = $_code;
            if (((stripos($_code, "paypal") !== false && stripos($_code, "express") !== false) || stripos($_code, "payflow") !== false || stripos($_code, "verisign") !== false || stripos($_code, "paypaluk_direct") !== false)) {
	return true;
            }
        }
        return false;
    }

    /**
     * Prepear Mask Address for Ship2MyId Order's
     * 
     * @param   String $firstname (Access Token)
     * @param   String $lastname
     * @param   String $_zipcode
     * @param   String $city 
     * @param   Int $region_id
     * @param   String $region_name
     * @param   String $countryCode
     * @param   String $receiver_type
     * @param   String $receiver_linkedin_id
     * @param   String $receiver_facebook_id
     * 
     * @return  Array $_shipping
     */          
    
    public function formatShippingData($firstname, $lastname, $_zipcode, $city, $region_id, $region_name, $countryCode, $receiver_type, $receiver_linkedin_id, $receiver_facebook_id) 
    {
        if (!empty($region_id)) {
            $regionModel = Mage::getModel("directory/region")->load($region_id);
            $region_name = $regionModel->getName();
        }
        $_confir_ship2 = Mage::getStoreConfig('clm24core/shippings/ship2myid_label');
        $_shipping = Array(
            "firstname" => $_confir_ship2,
            "lastname" => "service",
            "company" => "",
            "street" => Array(
	Mage::getStoreConfig('clm24core/shippings/street_line1'),
	Mage::getStoreConfig('clm24core/shippings/street_line2')
            ),
            "city" => $city,
            "region_id" => $region_id,
            "region" => $region_name,
            "postcode" => ((isset($_zipcode) && $_zipcode <> '') ? $_zipcode : Mage::getStoreConfig('clm24core/shippings/defaultZipCode')),
            "receiver_type",
            $receiver_type,
            "receiver_linkedin_id" => $receiver_linkedin_id,
            "receiver_facebook_id" => $receiver_facebook_id,
            "country_id" => $countryCode,
            "telephone" => Mage::getStoreConfig('clm24core/shippings/telephone'),
            "fax" => "",
            "save_in_address_book" => 0
        );
        return $_shipping;
    }
    
    /**
     * Return Ship2MyId Session Data
     * 
     * @return  Array
     */          

    protected function _getPopupdata() 
    {
        $session = Mage::getSingleton('checkout/session');
        return $session->getData('ship2myid');
    }

    /**
     * Save Ship2MyId Data to Order
     * 
     */          
    
    public function saveMyidshipping() 
    {
        $_quote = Mage::getModel('checkout/cart')->getQuote();

        //Mage::Log("Clm24 [Quote Before Update]: " . print_r($_quote->getData(), true));
        $data = $this->_getPopupdata();
        //Mage::Log("Clm24 [Quote After Update]: " . print_r($_quote->getData(), true));

        $_cml24Helper = Mage::Helper('myidshipping');
        $_clm24Session = $_cml24Helper->getMyIDSession();
        $_sender_email_address = $_quote->getData("customer_email");
        $_receiver_email_address = $data["email"];
        
        $receiver_type = $data['receiver_type'];
        $receiver_linkedin_id = ((isset($data['receiver_linkedin_id']) && $data['receiver_linkedin_id'] != "") ? $data['receiver_linkedin_id'] : "-");
        $receiver_facebook_id = ((isset($data['receiver_facebook_id']) && $data['receiver_facebook_id'] != "") ? $data['receiver_facebook_id'] : '-');
        
        $_zipcodeAndState = $_cml24Helper->getMyIDShippingSafeRecipientAddress($_clm24Session, $_sender_email_address, $_receiver_email_address,$receiver_type,$receiver_linkedin_id,$receiver_facebook_id);
        $temp_arr['max_shipping'] = '';
        if (is_array($_zipcodeAndState) && !isset($_zipcodeAndState['zipcode'])) {
            if (isset($_zipcodeAndState["status"]) && ($_zipcodeAndState["status"] == "520" || $_zipcodeAndState["status"] == "521")) {
	$_zipcodeAndState = null;
            } /* else {
              $result['error'] = $_zipcodeAndState["status"];
              throw new Exception("Invalid MyID Recipient");
              } */
        }
        $resource = Mage::getSingleton('core/resource');
        $readConnection = $resource->getConnection('core_read');
        $writeConnection = $resource->getConnection('core_write');

        $city = ((isset($data['city']) && $data['city'] <> '') ? $data['city'] : Mage::getStoreConfig('clm24core/shippings/city'));

        
        $firstname = $data['firstname'];
        $lastname = $data['lastname'];
        //Mage::Log("Clm24 [Name ]: " . $firstname . ' ' . $lastname);
        
        $country_code = ((isset($data['country_id']) && $data['country_id'] <> '') ? $data['country_id'] : Mage::getStoreConfig('clm24core/shippings/country_id'));

        if (empty($_zipcodeAndState)) {
            if (isset($data['postcode']) && $data['postcode'] != "") {
	$_zipcode = $data['postcode'];
	$session = Mage::getSingleton('checkout/session');
	$session->unsetData('ship2myid_maxship');
            } else {
	$_zipcode = Mage::getStoreConfig('clm24core/shippings/defaultZipCode');
	$session = Mage::getSingleton('checkout/session');
	$temp_arr['max_shipping'] = 1;
	$session->setData('ship2myid_maxship', $temp_arr);
            }
        } else {
            if (is_array($_zipcodeAndState) && isset($_zipcodeAndState['zipcode'])) {
	$_zipcode = $data['postcode'] = $_zipcodeAndState['zipcode'];
            } else {
	if (isset($data['postcode']) && $data['postcode'] != "") {
	    $_zipcode = $data['postcode'];
	} else {
	    $_zipcode = Mage::getStoreConfig('clm24core/shippings/defaultZipCode');
	}
            }
            $region_name = $data['region'] = $_zipcodeAndState['state'];
            $country_code = $data['country_id'] = $_zipcodeAndState['country'];
            $session = Mage::getSingleton('checkout/session');
            $session->unsetData('ship2myid_maxship');
        }

        if (isset($region_name) && $region_name != "") {
            $query = "SELECT region_id FROM `".$resource->getTableName('directory_country_region_name')."` WHERE name ='" . $region_name . "'";
            $regionid = $readConnection->fetchOne($query);
            if (isset($regionid) && $regionid != "") {
	$region_id = $regionid;
            } else {
	$region_name = $region_name;
            }
        } elseif (isset($data['region']) && $data['region'] != "") {
            $query = "SELECT region_id FROM `".$resource->getTableName('directory_country_region_name')."` WHERE name ='" . $data['region'] . "'";
            $regionid = $readConnection->fetchOne($query);
            if (isset($regionid) && $regionid != "") {
	$region_id = $regionid;
            } else {
	$region_name = $data['region'];
            }
        } else {
            $region_id = Mage::getStoreConfig('clm24core/shippings/region_id');
        }
        foreach ($data as $key => $value) {
            $_quote->setData($key, $value);
        }
        $address = $_quote->getShippingAddress();
        //$addressdata = $address->getData();
        //Mage::Log("Clm24 [Quote Shipping Address]: City : " . $addressdata['city'] . ' @ Postcode : ' . $addressdata['postcode'] . ' @ region : ' . $addressdata['region']);

        $addressForm = Mage::getModel('customer/form');
        $addressForm->setFormCode('customer_address_edit')->setEntity($address)->setEntityType('customer_address')->setIsAjaxRequest(Mage::app()->getRequest()->isAjax());
        $shippingaddr_data = $_cml24Helper->formatShippingData($firstname, $lastname, $_zipcode, $city, $region_id, $region_name, $country_code, $receiver_type, $receiver_linkedin_id, $receiver_facebook_id);
        $addressData = $addressForm->extractData($addressForm->prepareRequest($shippingaddr_data));
        $addressErrors = $addressForm->validateData($addressData);
        //Mage::Log("Clm24 [Address]: " . print_r($addressData, true));
        $addressForm->compactData($addressData);
        $address->implodeStreetAddress();
        $address->setCollectShippingRates(true);
        if (($validateRes = $address->validate()) !== true) {
            $result = array(
	'error' => 1,
	'message' => $validateRes
            );
        }
        $_quote->collectTotals()->save();

        //$address = $_quote->getShippingAddress();
        //$addressdata = $address->getData();
        //Mage::Log("Clm24 [Quote Shipping Address]: City : " . $addressdata['city'] . ' @ Postcode : ' . $addressdata['postcode'] . ' @ region : ' . $addressdata['region']);
        //Mage::Log("Clm24 [Quote After Update]: " . print_r($_quote->getData(), true));
        $_cml24Helper->closeMyIDSession($_clm24Session);
    }

    public function getMarketplaceDetails()
    {
        
        $marketplace_details = array();
        $session = Mage::getSingleton('core/session');
        $marketplace_details = $session->getMarketplaceDetails();

        if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
            Mage::Log("Clm24 [marketplace_details]: " . print_r($marketplace_details, true));
        }
        
        if(empty($marketplace_details) ){
            $marketplace_details = $this->_getMarketplaceDetails();
            $session->setMarketplaceDetails($marketplace_details);
        }
        return $marketplace_details;
        
    }
    
    private function _getMarketplaceDetails(){
        
        $_clm24Session = $this->getMyIDSession();
        $marketplace_details = array();
        $url = Mage::getStoreConfig('clm24core/shippings/clm24Url') . "/order/marketplacedetails?";
        $_requestUrl = $url . "access_token=" . $_clm24Session ;
        $ch = curl_init($_requestUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);
        if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
            Mage::Log("Clm24 [marketplace_details API Response]: " . print_r($response, true));
        }
        
        if ($response) {
            $marketplace_details = Zend_Json::decode($response);
        }
        return $marketplace_details;
    }
}