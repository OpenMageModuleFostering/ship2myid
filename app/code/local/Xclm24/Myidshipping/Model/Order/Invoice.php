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

class Xclm24_Myidshipping_Model_Order_Invoice extends Mage_Sales_Model_Order_Invoice
{
    const XML_PATH_EMAIL_TEMPLATE               = 'clm24_email/invoice/template';
    const XML_PATH_EMAIL_GUEST_TEMPLATE         = 'clm24_email/invoice/guest_template';
    
    public function sendEmail($notifyCustomer = true, $comment = '')
    {
        $order = $this->getOrder();
        $storeId = $order->getStore()->getId();

        if (!Mage::helper('sales')->canSendNewInvoiceEmail($storeId)) {
            return $this;
        }
        // Get the destination email addresses to send copies to
        $copyTo = $this->_getEmails(self::XML_PATH_EMAIL_COPY_TO);
        $copyMethod = Mage::getStoreConfig(self::XML_PATH_EMAIL_COPY_METHOD, $storeId);
        // Check if at least one recepient is found
        if (!$notifyCustomer && !$copyTo) {
            return $this;
        }

        // Start store emulation process
        $appEmulation = Mage::getSingleton('core/app_emulation');
        $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($storeId);

        try {
            // Retrieve specified view block from appropriate design package (depends on emulated store)
            $paymentBlock = Mage::helper('payment')->getInfoBlock($order->getPayment())
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
        if ($order->getCustomerIsGuest()) {
            $templateId = Mage::getStoreConfig(self::XML_PATH_EMAIL_GUEST_TEMPLATE, $storeId);
            $customerName = $order->getBillingAddress()->getName();
        } else {
            $templateId = Mage::getStoreConfig(self::XML_PATH_EMAIL_TEMPLATE, $storeId);
            $customerName = $order->getCustomerName();
        }

        $mailer = Mage::getModel('core/email_template_mailer');
        if ($notifyCustomer) {
            $emailInfo = Mage::getModel('core/email_info');
            $emailInfo->addTo($order->getCustomerEmail(), $customerName);
            if ($copyTo && $copyMethod == 'bcc') {
                // Add bcc to customer email
                foreach ($copyTo as $email) {
                    $emailInfo->addBcc($email);
                }
            }
            $mailer->addEmailInfo($emailInfo);
        }

        // Email copies are sent as separated emails if their copy method is 'copy' or a customer should not be notified
        if ($copyTo && ($copyMethod == 'copy' || !$notifyCustomer)) {
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
		$order_id = $order->getIncrementId(); 
		if (Mage::getStoreConfig('clm24core/shippings/debug') == 1)
			Mage::Log("Clm24 [Order_id]: ".print_r($order_id, true));
		
		
		$clm24_model = Mage::getModel("myidshipping/ordergrid")->getCollection()
                        ->addFieldToFilter('main_table.order_id', array('eq' => $order_id));
		$orderData = $clm24_model->getData();
		
		if (Mage::getStoreConfig('clm24core/shippings/debug') == 1)
			Mage::Log("Clm24 [ship2myid_order_data]: ".print_r($order_id, true));		
		
		$cnt = count($orderData);
		
		if (Mage::getStoreConfig('clm24core/shippings/debug') == 1)
			Mage::Log("Clm24 [ship2myid_order_data_counter]: ".print_r($cnt, true));		
		
		$flag_ship2myid = 0;
		
		if($cnt > 0){
			$flag_ship2myid = 1;

			if (Mage::getStoreConfig('clm24core/shippings/debug') == 1)
				Mage::Log("Clm24 [ship2myid-address]: ".print_r($clm24shipping_address, true));
		}		
        $mailer->setTemplateParams(array(
                'order'        => $order,
                'invoice'      => $this,
                'comment'      => $comment,
                'billing'      => $order->getBillingAddress(),
                'payment_html' => $paymentBlockHtml,
				'isship2myid' => $flag_ship2myid
            )
        );
        $mailer->send();
        $this->setEmailSent(true);
        $this->_getResource()->saveAttribute($this, 'email_sent');

        return $this;
    }
	
	  public function sendUpdateEmail($notifyCustomer = true, $comment = '')
    {
        $order = $this->getOrder();
        $storeId = $order->getStore()->getId();

        if (!Mage::helper('sales')->canSendInvoiceCommentEmail($storeId)) {
            return $this;
        }
        // Get the destination email addresses to send copies to
        $copyTo = $this->_getEmails(self::XML_PATH_UPDATE_EMAIL_COPY_TO);
        $copyMethod = Mage::getStoreConfig(self::XML_PATH_UPDATE_EMAIL_COPY_METHOD, $storeId);
        // Check if at least one recepient is found
        if (!$notifyCustomer && !$copyTo) {
            return $this;
        }

        // Retrieve corresponding email template id and customer name
        if ($order->getCustomerIsGuest()) {
            $templateId = Mage::getStoreConfig(self::XML_PATH_UPDATE_EMAIL_GUEST_TEMPLATE, $storeId);
            $customerName = $order->getBillingAddress()->getName();
        } else {
            $templateId = Mage::getStoreConfig(self::XML_PATH_UPDATE_EMAIL_TEMPLATE, $storeId);
            $customerName = $order->getCustomerName();
        }

        $mailer = Mage::getModel('core/email_template_mailer');
        if ($notifyCustomer) {
            $emailInfo = Mage::getModel('core/email_info');
            $emailInfo->addTo($order->getCustomerEmail(), $customerName);
            if ($copyTo && $copyMethod == 'bcc') {
                // Add bcc to customer email
                foreach ($copyTo as $email) {
                    $emailInfo->addBcc($email);
                }
            }
            $mailer->addEmailInfo($emailInfo);
        }

        // Email copies are sent as separated emails if their copy method is 'copy' or a customer should not be notified
        if ($copyTo && ($copyMethod == 'copy' || !$notifyCustomer)) {
            foreach ($copyTo as $email) {
                $emailInfo = Mage::getModel('core/email_info');
                $emailInfo->addTo($email);
                $mailer->addEmailInfo($emailInfo);
            }
        }

        // Set all required params and send emails
        $mailer->setSender(Mage::getStoreConfig(self::XML_PATH_UPDATE_EMAIL_IDENTITY, $storeId));
        $mailer->setStoreId($storeId);
        $mailer->setTemplateId($templateId);
		
		//ship2myid Email Hide
		$order_id = $order->getIncrementId(); 
		if (Mage::getStoreConfig('clm24core/shippings/debug') == 1)
			Mage::Log("Clm24 [Order_id]: ".print_r($order_id, true));
		
		
		$clm24_model = Mage::getModel("myidshipping/ordergrid")->getCollection()
                        ->addFieldToFilter('main_table.order_id', array('eq' => $order_id));
		$orderData = $clm24_model->getData();
		
		if (Mage::getStoreConfig('clm24core/shippings/debug') == 1)
			Mage::Log("Clm24 [ship2myid_order_data]: ".print_r($order_id, true));		
		
		$cnt = count($orderData);
		
		if (Mage::getStoreConfig('clm24core/shippings/debug') == 1)
			Mage::Log("Clm24 [ship2myid_order_data_counter]: ".print_r($cnt, true));		
		
		$clm24shipping_address = '';
		$flag_ship2myid = 0;
		
		if($cnt > 0){
			$clm24shipping_address = $clm24shipping_address . Mage::getStoreConfig('clm24core/shippings/ship2myid_label') . "<br>";
			$clm24shipping_address = $clm24shipping_address . Mage::getStoreConfig('clm24core/shippings/street_line1') . "<br>";
			$clm24shipping_address = $clm24shipping_address . Mage::getStoreConfig('clm24core/shippings/street_line2') . "<br>";
			$clm24shipping_address = $clm24shipping_address . Mage::getStoreConfig('clm24core/shippings/city') . "<br>";
			$clm24shipping_address = $clm24shipping_address . Mage::getStoreConfig('clm24core/shippings/defaultZipCode') . "<br>";
			$clm24shipping_address = $clm24shipping_address . Mage::getStoreConfig('clm24core/shippings/country_id') . "<br>";
			$flag_ship2myid = 1; 	
			
			if (Mage::getStoreConfig('clm24core/shippings/debug') == 1)
				Mage::Log("Clm24 [ship2myid-address]: ".print_r($clm24shipping_address, true));	
		}		
		
        $mailer->setTemplateParams(array(
                'order'        => $order,
                'invoice'      => $this,
                'comment'      => $comment,
                'billing'      => $order->getBillingAddress(),
				'shipping'	   => $clm24shipping_address,
				'isship2myid' => $flag_ship2myid
            )
        );
        $mailer->send();

        return $this;
    }

}
