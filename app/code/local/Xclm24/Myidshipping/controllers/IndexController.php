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
 * Index controller for Myidshipping
 */

class Xclm24_Myidshipping_IndexController extends Mage_Core_Controller_Front_Action 
{

    /**
     * Return Session MyidPopHandlerData 
     *
     * @return array()
     */
    
    protected function _getPopupdata() 
    {
        return Mage::getSingleton('core/session')->getMyidPopHandlerData();
    }

    /**
     * Get Popup handler html
     *
     * @return String
     */
    
    protected function _getMyidPopHandlerHtml() 
    {

        $layout = $this->getLayout();
        $update = $layout->getUpdate();
        $update->load('midshipping_myid_popuphandler');
        $layout->generateXml();
        $layout->generateBlocks();
        $output = $layout->getOutput();

        return $output;
    }

    /**
     * This function is called from the Ship2MyId Server 
     * to send receiver data to magento which is fill be 
     * sender on popup page. 
     * Save the data in session
     *
     * @return string
     */
    
    public function postbackAction() 
    {
        /* if($_POST['receiver_linkedin_id']!=""){
          $_POST['receiver_type']='linkedin';
          }elseif($_POST['receiver_facebook_id']!=""){
          $_POST['receiver_type']='facebook';
          }else{
          $_POST['receiver_type']='email';
          } */
        
        $post_data = $_POST;
        $post_data['firstname_by_server'] =  $post_data['firstname'];
        $post_data['lastname_by_server'] =  $post_data['lastname'];
        $post_data['username_by_server'] =  $post_data['username'];
        
        $name = explode(' ', $post_data['firstname']);
        
        if (is_array($name) && !empty($name)) {
            $post_data['firstname'] = $name[0];
            $post_data['lastname'] = isset($name[1]) && !empty($name[1]) ? $name[1] : $name[0];
            $post_data['username'] = $post_data['firstname']. ' ' . $post_data['lastname'];
        }else{
            $post_data['firstname'] = $post_data['firstname'];
            $post_data['lastname'] = $post_data['lastname'];
        }
        
        
        $session = Mage::getSingleton('checkout/session');
        $session->setData('ship2myid', $post_data);
        if (Mage::getStoreConfig('clm24core/shippings/debug') == 1) {
            Mage::log('Ship2MyId Post Back Log'.  print_r($post_data,TRUE));
        }
        //Mage::getSingleton('core/session')->setMyidPopHandlerData($_POST);
        echo $this->_getMyidPopHandlerHtml();
    }

    /**
     * This function is use to apply ship2myid 
     * specific coupon to cart 
     * 
     * @return string
     */
    
    public function checkcouponAction() 
    {

        $couponCodealready = Mage::getSingleton('checkout/cart')->getQuote()->getCouponCode();
        if ($couponCodealready == "") {
            $couponCode = (string) $_POST['coupon_code'];
            if (isset($couponCode) && $couponCode != "") {
                $oCoupon = Mage::getModel('salesrule/coupon')->load($couponCode, 'code');
                $oRule = Mage::getModel('salesrule/rule')->load($oCoupon->getRuleId());
                $ruledata = $oRule->getData();

                if (isset($ruledata['rule_id']) && $ruledata['rule_id'] != "") {
                    $clm24myidCouponModel = Mage::getModel('myidshipping/coupon')->loadByRuleId($ruledata['rule_id']);
                    if ($clm24myidCouponModel->getShip2myidOnly() == 1) {

                        $result['error'] = 0;
                        $result['message'] = $this->__('coupon code is valid for shim2myid');
                    } else {
                        $result['error'] = 1;
                        $result['message'] = $this->__('coupon code is invalid for shim2myid');
                        $couponmsg = "this coupon code is invalid for shim2myid";
                    }
                    //zend_debug::Dump($clm24myidCouponModel->getShip2myidOnly());
                } else {
                    $result['error'] = 1;
                    $result['message'] = $this->__('coupon code is not valid');
                }
            } else {

                $result['error'] = 1;

                $result['message'] = $this->__('you have already applied a coupon code for this order');
            }
        }
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }

}