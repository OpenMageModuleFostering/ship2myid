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
 * Gift message inline edit form
 */

class Xclm24_Myidshipping_Block_GiftMessage_Message_Inline extends Mage_GiftMessage_Block_Message_Inline
{
    /**
     * Get default value for To field
     *
     * @return string
     */
    public function getDefaultTo()
    {
        if(Mage::getStoreConfig('clm24core/shippings/enabled')):
            if ($shippingAddress = $this->getEntity()->getShippingAddress()) {
                $shippingAddressEmail = $shippingAddress->getEmail();
                $_clm24 = Mage::getSingleton('checkout/session')->getData("ship2myid");
                if(!empty($_clm24) && array_key_exists($shippingAddressEmail,$_clm24)){
                    $recData = $_clm24[$shippingAddressEmail];
                    return $recData['firstname'].' '.$recData['lastname'];
                }elseif(!empty($_clm24) && array_key_exists('firstname',$_clm24)){
                    return $_clm24['firstname'].' '.$_clm24['lastname'];
                }else{
                    return $this->getEntity()->getShippingAddress()->getName();
                }
            } else {
                $shippingAddress = $this->getEntity();
                $shippingAddressEmail = $shippingAddress->getEmail();
                $_clm24 = Mage::getSingleton('checkout/session')->getData("ship2myid");
                if(!empty($_clm24) && array_key_exists($shippingAddressEmail,$_clm24)){
                    $recData = $_clm24[$shippingAddressEmail];
                    return $recData['Myidfirstname'].' '.$recData['Myidlastname'];
                }elseif(!empty($_clm24) && array_key_exists('firstname',$_clm24)){
                    return $_clm24['firstname'].' '.$_clm24['lastname'];
                }else{
                    return $this->getEntity()->getName();
                }
            }
        else:
            if ($this->getEntity()->getShippingAddress()) {
                return $this->getEntity()->getShippingAddress()->getName();
            } else {
                return $this->getEntity()->getName();
            }
        endif;
    }

}
