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
 * Myidshipping Order Model
 * 
 */
ini_set('memory_limit', '1024M');
class Xclm24_Myidshipping_Model_Order extends Mage_Sales_Model_Order
{

    /**
     * Ship2MyId Email Template Path
     * 
     */
    
    const XML_PATH_EMAIL_TEMPLATE               = 'clm24_email/order/template';

    /**
     * Ship2MyId Email Template Path for Guest Order
     * 
     */
    
    const XML_PATH_EMAIL_GUEST_TEMPLATE         = 'clm24_email/order/guest_template';
    
    /**
     * Ship2MyId Order Data
     * 
     */
    
    protected $_clm24OrderData = null;

    /**
     * Send email with order data
     *
     * @return Mage_Sales_Model_Order
     */
    
    public function sendNewOrderEmail()
    {
		
        $storeId = $this->getStore()->getId();

        if (!Mage::helper('sales')->canSendNewOrderEmail($storeId)) {
            return $this;
        }
        // Get the destination email addresses to send copies to
        $copyTo = $this->_getEmails(self::XML_PATH_EMAIL_COPY_TO);
        $copyMethod = Mage::getStoreConfig(self::XML_PATH_EMAIL_COPY_METHOD, $storeId);

        // Start store emulation process
        $appEmulation = Mage::getSingleton('core/app_emulation');
        $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($storeId);

        try {
            // Retrieve specified view block from appropriate design package (depends on emulated store)
            $paymentBlock = Mage::helper('payment')->getInfoBlock($this->getPayment())
                ->setIsSecureMode(true);
            $paymentBlock->getMethod()->setStore($storeId);
            $paymentBlockHtml = $paymentBlock->toHtml();
        } catch (Exception $exception) {
            // Stop store emulation process
            $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
            throw $exception;
        }

        // Stop store emulation process
        $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);

        // Retrieve corresponding email template id and customer name
        if ($this->getCustomerIsGuest()) {
            $templateId = Mage::getStoreConfig(self::XML_PATH_EMAIL_GUEST_TEMPLATE, $storeId);
            $customerName = $this->getBillingAddress()->getName();
        } else {
            $templateId = Mage::getStoreConfig(self::XML_PATH_EMAIL_TEMPLATE, $storeId);
            $customerName = $this->getCustomerName();
        }

        $mailer = Mage::getModel('core/email_template_mailer');
        $emailInfo = Mage::getModel('core/email_info');
        $emailInfo->addTo($this->getCustomerEmail(), $customerName);
        if ($copyTo && $copyMethod == 'bcc') {
            // Add bcc to customer email
            foreach ($copyTo as $email) {
                $emailInfo->addBcc($email);
            }
        }
        $mailer->addEmailInfo($emailInfo);

        // Email copies are sent as separated emails if their copy method is 'copy'
        if ($copyTo && $copyMethod == 'copy') {
            foreach ($copyTo as $email) {
                $emailInfo = Mage::getModel('core/email_info');
                $emailInfo->addTo($email);
                $mailer->addEmailInfo($emailInfo);
            }
        }

        // Set all required params and send emails
        $mailer->setSender(Mage::getStoreConfig(self::XML_PATH_EMAIL_IDENTITY, $storeId));
        $mailer->setStoreId($storeId);
        $mailer->setTemplateId($templateId);
		
		//ship2myid Email Hide
		$order_id = $this->getIncrementId(); 
		if (Mage::getStoreConfig('clm24core/shippings/debug') == 1)
			Mage::Log("Clm24 [Order_id]: ".print_r($order_id, true));
		
		
		$clm24_model = Mage::getModel("myidshipping/ordergrid")->getCollection()
                        ->addFieldToFilter('main_table.order_id', array('eq' => $order_id));
		$orderData = $clm24_model->getData();
		//zend_debug::dump($orderData);
		
		if (Mage::getStoreConfig('clm24core/shippings/debug') == 1)
			Mage::Log("Clm24 [ship2myid_order_data]: ".print_r($order_id, true));		
		
		$cnt = count($orderData);
		
		if (Mage::getStoreConfig('clm24core/shippings/debug') == 1)
			Mage::Log("Clm24 [ship2myid_order_data_counter]: ".print_r($cnt, true));		
		
		$clm24shipping_address = '';
		$flag_ship2myid = 0;

        $shipingAddress = $this->getShippingAddress();
		
		
        if($shipingAddress->getFirstname() == Mage::getStoreConfig('clm24core/shippings/ship2myid_label')){
            //$quote = $this->getQuote();
            $quote = Mage::getModel('sales/quote')->load($this->getQuoteId());
            $isMultishippingShip2myid = false;
            if($quote->getIsMultiShipping()){
                $isMultishippingShip2myid = true;
            }
        }
		 
		
        if($cnt > 0){
			
			$resource = Mage::getSingleton('core/resource');
			$readConnection = $resource->getConnection('core_read');
			$writeConnection = $resource->getConnection('core_write');
			$table = $resource->getTableName('myidshipping/shipping');
			$query = "select value from ".$table." where order_grid_id ='".$orderData[0]["entity_id"]."' and (attribute ='postcode' or attribute ='zipcode')";			
			$postcode = $readConnection->fetchOne($query);
			$postcode = ( !is_null($postcode) && !empty($postcode))? $postcode : Mage::getStoreConfig('clm24core/shippings/defaultZipCode');
			
			$querynew = "select value from ".$table." where order_grid_id ='".$orderData[0]["entity_id"]."' and (attribute ='country_id' or attribute ='countryCode')";			
			$country_id = $readConnection->fetchOne($querynew);
			$country_id = ( !is_null($country_id) && !empty($country_id))? $country_id : Mage::getStoreConfig('clm24core/shippings/country_id');
			
			$countryModel = Mage::getModel('directory/country')->loadByCode($country_id);

			$countryName = $countryModel->getName();		
 
			$clm24shipping_address = $clm24shipping_address . Mage::getStoreConfig('clm24core/shippings/ship2myid_label') . "<br>";
			$clm24shipping_address = $clm24shipping_address . Mage::getStoreConfig('clm24core/shippings/street_line1') . "<br>";
			$clm24shipping_address = $clm24shipping_address . Mage::getStoreConfig('clm24core/shippings/street_line2') . "<br>";
			$clm24shipping_address = $clm24shipping_address . Mage::getStoreConfig('clm24core/shippings/city') . "<br>";
			$clm24shipping_address = $clm24shipping_address . $postcode . "<br>";
			$clm24shipping_address = $clm24shipping_address . $countryName . "<br>";
			
			$flag_ship2myid = 1; 	
			
			if (Mage::getStoreConfig('clm24core/shippings/debug') == 1)
				Mage::Log("Clm24 [ship2myid-address]: ".print_r($clm24shipping_address, true));	
		}
		else{
			
			$countryModel = Mage::getModel('directory/country')->loadByCode($shipingAddress->getCountry_id());

 $countryName = $countryModel->getName();		
 
			$clm24shipping_address = $clm24shipping_address . $shipingAddress->getName() . "<br>";
			$clm24shipping_address = $clm24shipping_address . $shipingAddress->getStreetFull() . "<br>";
			$clm24shipping_address = $clm24shipping_address . $shipingAddress->getCity(). "<br>";
			$clm24shipping_address = $clm24shipping_address . $shipingAddress->getRegion() . "<br>";			
			$clm24shipping_address = $clm24shipping_address . $shipingAddress->getPostcode() . "<br>";
			$clm24shipping_address = $clm24shipping_address . $countryName . "<br>";
			$clm24shipping_address = $clm24shipping_address . "T : " .$shipingAddress->getTelephone() . "<br>";
		}
		
        $mailer->setTemplateParams(array(
                'order'        => $this,
                'billing'      => $this->getBillingAddress(),
                'payment_html' => $paymentBlockHtml,
				'shipping'	   => $clm24shipping_address,
				'isship2myid' => $flag_ship2myid
            )
        );
        $mailer->send();

        $this->setEmailSent(true);
        $this->_getResource()->saveAttribute($this, 'email_sent');

        return $this;
    }

    /**
     * Retrieve order shipping address
     *
     * @return Mage_Sales_Model_Order_Address
     */
    public function getShippingAddress()
    {
        foreach ($this->getAddressesCollection() as $address) {
            if ($address->getAddressType()=='shipping' && !$address->isDeleted()) {
                return $address;
            }
        }
        return false;
    }


     /**
     * Order state protected setter.
     * By default allows to set any state. Can also update status to default or specified value
     * Сomplete and closed states are encapsulated intentionally, see the _checkState()
     *
     * @param string $state
     * @param string|bool $status
     * @param string $comment
     * @param bool $isCustomerNotified
     * @param $shouldProtectState
     * @return Mage_Sales_Model_Order
     */
    protected function _setState($state, $status = false, $comment = '',
        $isCustomerNotified = null, $shouldProtectState = false)
    {
        // attempt to set the specified state
        if ($shouldProtectState) {
            if ($this->isStateProtected($state)) {
                Mage::throwException(
                    Mage::helper('sales')->__('The Order State "%s" must not be set manually.', $state)
                );
            }
        }
        $this->setData('state', $state);

        // add status history
        if ($status) {
            if ($status === true) {
                $status = $this->getConfig()->getStateDefaultStatus($state);
            }
            $this->setStatus($status);
            $history = $this->addStatusHistoryComment($comment, false); // no sense to set $status again
            $history->setIsCustomerNotified($isCustomerNotified); // for backwards compatibility

            /*
             *  const STATE_COMPLETE        = 'complete';
                const STATE_CLOSED          = 'closed';
                const STATE_CANCELED        = 'canceled';
             */
            switch ($state)
            {
                case 'complete':
                case 'closed':
                {
                    $_cml24Helper = Mage::Helper('myidshipping');
                    $clm24_token = $_cml24Helper->getMyIDSession();
                    $_clm24Order = $_cml24Helper->completeOrder($clm24_token, $this->getIncrementId());
                    $_cml24Helper->closeMyIDSession($clm24_token);
                }
                break;
				case 'canceled':
				{
                    $_cml24Helper = Mage::Helper('myidshipping');
                    $clm24_token = $_cml24Helper->getMyIDSession();
                    $_clm24Order = $_cml24Helper->cancelOrder($clm24_token, $this->getIncrementId());
				    $_cml24Helper->closeMyIDSession($clm24_token);
                }
				break;
                default:
                {}
                break;
            }
        }
        return $this;
    }

     /**
     * Check whether Order is Ship2MyId Order or Not
     *
     * @return boolean
     */
    
    public function isShip2myid()
    {
		
        $clm24_model = Mage::getModel("myidshipping/ordergrid")->getCollection()
	->addFieldToFilter('main_table.order_id', array('eq' => $this->getIncrementId()));

        $orderData = $clm24_model->getData();

        if (count($orderData) > 0) {

            $this->_clm24OrderData = $orderData[0];
            return true;
        } else {

            $shipingAddress = $this->getShippingAddress();

            if ($shipingAddress->getFirstname() == Mage::getStoreConfig('clm24core/shippings/ship2myid_label')) {

	//$quote = $this->getQuote();
	$quote = Mage::getModel('sales/quote')->load($this->getQuoteId());
	if ($quote->getIsMultiShipping()) {
	    $session = Mage::getSingleton('checkout/session');
	    $receiverData = $session->getData('ship2myidReceiver');
	    $_clm24Data = $receiverData[$shipingAddress->getEmail()];
	    $this->_clm24OrderData = array('rec_fname_by_sender' => $_clm24Data['firstname'], 'rec_email_by_sender' => $_clm24Data['email'], 'rec_lname_by_sender' => $_clm24Data['lastname']);
	    return true;
	}
            }
        }
        return false;
    }

     /**
     * Retun Ship2MyId Order firstname
     *
     * @return String
     */
    
    public function get_ship2myid_order_firstname()
    {
        if ($arr_ship2myid = Mage::getSingleton('core/session')->getShip2myid() && isset($arr_ship2myid['firstname']) && $arr_ship2myid['firstname'] != '') {
            return $arr_ship2myid['firstname'];
        } else {
            $clm24orderData = $this->_clm24OrderData;
            $resource = Mage::getSingleton('core/resource');
            $readConnection = $resource->getConnection('core_read');
            $writeConnection = $resource->getConnection('core_write');
            $table = $resource->getTableName('myidshipping/shipping');
            $query = "select value from " . $table . " where order_grid_id ='" . $clm24orderData["entity_id"] . "' and (attribute ='firstname' or attribute ='receiver_first_name')";
            $firstname = $readConnection->fetchOne($query);
            return $firstname;
        }
    }

     /**
     * Retun Ship2MyId Order email
     *
     * @return String
     */
    
    public function get_ship2myid_order_email()
    {
        if ($arr_ship2myid = Mage::getSingleton('core/session')->getShip2myid() && isset($arr_ship2myid['email']) && $arr_ship2myid['email'] != '') {
            return $arr_ship2myid['email'];
        } else {

            $clm24orderData = $this->_clm24OrderData;
            $resource = Mage::getSingleton('core/resource');
            $readConnection = $resource->getConnection('core_read');
            $writeConnection = $resource->getConnection('core_write');
            $table = $resource->getTableName('myidshipping/shipping');

            $query = "select value from " . $table . " where order_grid_id ='" . $clm24orderData["entity_id"] . "' and (attribute ='email' or attribute ='receiver_email_address')";

            $email = $readConnection->fetchOne($query);
            return $email;
        }
    }

    /**
     * Retun Ship2MyId Order Lastname
     *
     * @return String
     */
    
    public function get_ship2myid_order_lastname()
    {
        if ($arr_ship2myid = Mage::getSingleton('core/session')->getShip2myid() && isset($arr_ship2myid['lastname']) && $arr_ship2myid['lastname'] != '') {
            return $arr_ship2myid['lastname'];
        } else {
            $clm24orderData = $this->_clm24OrderData;
            $resource = Mage::getSingleton('core/resource');
            $readConnection = $resource->getConnection('core_read');
            $writeConnection = $resource->getConnection('core_write');
            $table = $resource->getTableName('myidshipping/shipping');

            $query = "select value from " . $table . " where order_grid_id ='" . $clm24orderData["entity_id"] . "' and (attribute ='lastname' or attribute ='receiver_last_name')";
            $lastname = $readConnection->fetchOne($query);
            return $lastname;
        }
    }

     /**
     * Retun Ship2MyId Order PostCode
     *
     * @return String
     */
    
    public function get_ship2myid_order_postcode()
    {
        
        $clm24orderData = $this->_clm24OrderData;
        $resource = Mage::getSingleton('core/resource');
        $readConnection = $resource->getConnection('core_read');
        $writeConnection = $resource->getConnection('core_write');
        $table = $resource->getTableName('myidshipping/shipping');

        $query = "select value from " . $table . " where order_grid_id ='" . $clm24orderData["entity_id"] . "' and (attribute ='postcode' or attribute ='zipcode')";
        $postcode = $readConnection->fetchOne($query);
        return $postcode;
    }

     /**
     * Retun Ship2MyId Order Country
     *
     * @return String
     */
    
    public function get_ship2myid_order_country()
    {
        
        $clm24orderData = $this->_clm24OrderData;
        $resource = Mage::getSingleton('core/resource');
        $readConnection = $resource->getConnection('core_read');
        $writeConnection = $resource->getConnection('core_write');
        $table = $resource->getTableName('myidshipping/shipping');

        $query = "select value from " . $table . " where order_grid_id ='" . $clm24orderData["entity_id"] . "' and (attribute ='country_id' or attribute ='countryCode')";
        $country_id = $readConnection->fetchOne($query);
        $countryModel = Mage::getModel('directory/country')->loadByCode($country_id);

        $countryName = $countryModel->getName();
        return $countryName;
    }

     /**
     * Retun Ship2MyId Order Original Shipping Address
     *
     * @return String
     */
    
    public function get_original_shipping_address()
    {
        $clm24shipping_address = '';
        $shipingAddress = $this->getShippingAddress();
        $countryModel = Mage::getModel('directory/country')->loadByCode($shipingAddress->getCountry_id());

        $countryName = $countryModel->getName();

        $clm24shipping_address = $clm24shipping_address . $shipingAddress->getName() . "<br>";
        $clm24shipping_address = $clm24shipping_address . $shipingAddress->getStreetFull() . "<br>";
        $clm24shipping_address = $clm24shipping_address . $shipingAddress->getCity() . "<br>";
        $clm24shipping_address = $clm24shipping_address . $shipingAddress->getRegion() . "<br>";
        $clm24shipping_address = $clm24shipping_address . $shipingAddress->getPostcode() . "<br>";
        $clm24shipping_address = $clm24shipping_address . $countryName . "<br>";
        $clm24shipping_address = $clm24shipping_address . "T : " . $shipingAddress->getTelephone() . "<br>";
        return $clm24shipping_address;
    }

     /**
     * Retun Ship2MyId Order Receiver FirstName
     *
     * @return String
     */
    
    public function get_ship2myid_firstname()
    {
        $clm24orderData = $this->_clm24OrderData;
        if (isset($clm24orderData['rec_fname_by_sender']) && $clm24orderData['rec_fname_by_sender'] != NULL && $clm24orderData['rec_fname_by_sender'] != '') {
            return $clm24orderData['rec_fname_by_sender'];
        } else {
            return $this->getShippingAddress()->getFirstname();
        }
    }

     /**
     * Retun Ship2MyId Order Receiver LastName
     *
     * @return String
     */
    
    public function get_ship2myid_lastname()
    {
        $clm24orderData = $this->_clm24OrderData;
        if (isset($clm24orderData['rec_lname_by_sender']) && $clm24orderData['rec_lname_by_sender'] != NULL && $clm24orderData['rec_lname_by_sender'] != '') {
            return $clm24orderData['rec_lname_by_sender'];
        } else {
            return $this->getShippingAddress()->getLastname();
        }
    }

}