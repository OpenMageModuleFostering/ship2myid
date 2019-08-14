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
 * Myidshipping PayPal Standard API Model
 * 
 */
class Xclm24_Myidshipping_Model_Api_Standard extends Mage_Paypal_Model_Api_Standard 
{

    /**
     * Import Address
     * @param   Array Reference
     * 
     */       
    protected function _importAddress(&$request)
    {
        $address = $this->getAddress();
        if (!$address) {
            if ($this->getNoShipping()) {
	$request['no_shipping'] = 1;
            }
            return;
        }

        $request = Varien_Object_Mapper::accumulateByMap($address, $request, array_flip($this->_addressMap));

        // Address may come without email info (user is not always required to enter it), so add email from order
        if (!$request['email']) {
            $order = $this->getOrder();
            if ($order) {
	$request['email'] = $order->getCustomerEmail();
            }
        }

        $regionCode = $this->_lookupRegionCodeFromAddress($address);
        if ($regionCode) {
            $request['state'] = $regionCode;
        }
        $this->_importStreetFromAddress($address, $request, 'address1', 'address2');
        $this->_applyCountryWorkarounds($request);

        //paypal fix for standard paypal options.
        $request['address_override'] = 0;
        //no need for shipping address
        $request['no_shipping'] = 1;
    }

}
