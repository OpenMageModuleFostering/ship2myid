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
 * Shopping Cart Price Rule General Information Tab
 */

class Xclm24_Myidshipping_Block_Adminhtml_Promo_Quote_Edit_Tab_Main extends Mage_Adminhtml_Block_Promo_Quote_Edit_Tab_Main
{

    /**
     * Add Ship2MyId Coupon Code condition in Form
     * 
     * @return Mage_Adminhtml_Block_Widget_Form
     */  
    
    protected function _prepareForm()
    {
        $model = Mage::registry('current_promo_quote_rule');

        $form = new Varien_Data_Form();
        $form->setHtmlIdPrefix('rule_');

        $fieldset = $form->addFieldset('base_fieldset',
            array('legend' => Mage::helper('salesrule')->__('General Information'))
        );

        if ($model->getId()) {
            $fieldset->addField('rule_id', 'hidden', array(
                'name' => 'rule_id',
            ));
        }

        $fieldset->addField('product_ids', 'hidden', array(
            'name' => 'product_ids',
        ));

        $fieldset->addField('name', 'text', array(
            'name' => 'name',
            'label' => Mage::helper('salesrule')->__('Rule Name'),
            'title' => Mage::helper('salesrule')->__('Rule Name'),
            'required' => true,
        ));

        $fieldset->addField('description', 'textarea', array(
            'name' => 'description',
            'label' => Mage::helper('salesrule')->__('Description'),
            'title' => Mage::helper('salesrule')->__('Description'),
            'style' => 'height: 100px;',
        ));

        $fieldset->addField('is_active', 'select', array(
            'label'     => Mage::helper('salesrule')->__('Status'),
            'title'     => Mage::helper('salesrule')->__('Status'),
            'name'      => 'is_active',
            'required' => true,
            'options'    => array(
                '1' => Mage::helper('salesrule')->__('Active'),
                '0' => Mage::helper('salesrule')->__('Inactive'),
            ),
        ));
        if(Mage::getStoreConfig('clm24core/shippings/enabled'))
        {
            if ($model->getId()) {
                $clm24myidCouponModel = Mage::getModel('myidshipping/coupon')->loadByRuleId($model->getId());
                if($clm24myidCouponModel->getShip2myidOnly()){
                    $clm24value = $clm24myidCouponModel->getShip2myidOnly();
                }else{
                    $clm24value = 0;
                }
                $model->setShip2myidOnly($clm24value);
            }
            $fieldset->addField('ship2myid_only', 'select', array(
                'label'     => Mage::helper('salesrule')->__('Use for Ship2myId only'),
                'title'     => Mage::helper('salesrule')->__('Use for Ship2myId only'),
                'name'      => 'ship2myid_only',
                'required'  => false,
                'value'     => $clm24value,
                'options'    => array(
                    1 => Mage::helper('salesrule')->__('Yes'),
                    0 => Mage::helper('salesrule')->__('No'),
                ),
            ));
        }

        if (!$model->getId()) {
            $model->setData('is_active', '1');
        }

        if (Mage::app()->isSingleStoreMode()) {
            $websiteId = Mage::app()->getStore(true)->getWebsiteId();
            $fieldset->addField('website_ids', 'hidden', array(
                'name'     => 'website_ids[]',
                'value'    => $websiteId
            ));
            $model->setWebsiteIds($websiteId);
        } else {
            $field = $fieldset->addField('website_ids', 'multiselect', array(
                'name'     => 'website_ids[]',
                'label'     => Mage::helper('salesrule')->__('Websites'),
                'title'     => Mage::helper('salesrule')->__('Websites'),
                'required' => true,
                'values'   => Mage::getSingleton('adminhtml/system_store')->getWebsiteValuesForForm()
            ));
            $renderer = $this->getLayout()->createBlock('adminhtml/store_switcher_form_renderer_fieldset_element');
            $field->setRenderer($renderer);
        }

        $customerGroups = Mage::getResourceModel('customer/group_collection')->load()->toOptionArray();
        $found = false;

        foreach ($customerGroups as $group) {
            if ($group['value']==0) {
                $found = true;
            }
        }
        if (!$found) {
            array_unshift($customerGroups, array(
                'value' => 0,
                'label' => Mage::helper('salesrule')->__('NOT LOGGED IN'))
            );
        }

        $fieldset->addField('customer_group_ids', 'multiselect', array(
            'name'      => 'customer_group_ids[]',
            'label'     => Mage::helper('salesrule')->__('Customer Groups'),
            'title'     => Mage::helper('salesrule')->__('Customer Groups'),
            'required'  => true,
            'values'    => Mage::getResourceModel('customer/group_collection')->toOptionArray(),
        ));

        $couponTypeFiled = $fieldset->addField('coupon_type', 'select', array(
            'name'       => 'coupon_type',
            'label'      => Mage::helper('salesrule')->__('Coupon'),
            'required'   => true,
            'options'    => Mage::getModel('salesrule/rule')->getCouponTypes(),
        ));

        $couponCodeFiled = $fieldset->addField('coupon_code', 'text', array(
            'name' => 'coupon_code',
            'label' => Mage::helper('salesrule')->__('Coupon Code'),
            'required' => true,
        ));

        $autoGenerationCheckbox = $fieldset->addField('use_auto_generation', 'checkbox', array(
            'name'  => 'use_auto_generation',
            'label' => Mage::helper('salesrule')->__('Use Auto Generation'),
            'note'  => Mage::helper('salesrule')->__('If you select and save the rule you will be able to generate multiple coupon codes.'),
            'onclick' => 'handleCouponsTabContentActivity()',
            'checked' => (int)$model->getUseAutoGeneration() > 0 ? 'checked' : ''
        ));

        $autoGenerationCheckbox->setRenderer(
            $this->getLayout()->createBlock('adminhtml/promo_quote_edit_tab_main_renderer_checkbox')
        );

        $usesPerCouponFiled = $fieldset->addField('uses_per_coupon', 'text', array(
            'name' => 'uses_per_coupon',
            'label' => Mage::helper('salesrule')->__('Uses per Coupon'),
        ));

        $fieldset->addField('uses_per_customer', 'text', array(
            'name' => 'uses_per_customer',
            'label' => Mage::helper('salesrule')->__('Uses per Customer'),
        ));

        $dateFormatIso = Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT);
        $fieldset->addField('from_date', 'date', array(
            'name'   => 'from_date',
            'label'  => Mage::helper('salesrule')->__('From Date'),
            'title'  => Mage::helper('salesrule')->__('From Date'),
            'image'  => $this->getSkinUrl('images/grid-cal.gif'),
            'input_format' => Varien_Date::DATE_INTERNAL_FORMAT,
            'format'       => $dateFormatIso
        ));
        $fieldset->addField('to_date', 'date', array(
            'name'   => 'to_date',
            'label'  => Mage::helper('salesrule')->__('To Date'),
            'title'  => Mage::helper('salesrule')->__('To Date'),
            'image'  => $this->getSkinUrl('images/grid-cal.gif'),
            'input_format' => Varien_Date::DATE_INTERNAL_FORMAT,
            'format'       => $dateFormatIso
        ));

        $fieldset->addField('sort_order', 'text', array(
            'name' => 'sort_order',
            'label' => Mage::helper('salesrule')->__('Priority'),
        ));

        $fieldset->addField('is_rss', 'select', array(
            'label'     => Mage::helper('salesrule')->__('Public In RSS Feed'),
            'title'     => Mage::helper('salesrule')->__('Public In RSS Feed'),
            'name'      => 'is_rss',
            'options'   => array(
                '1' => Mage::helper('salesrule')->__('Yes'),
                '0' => Mage::helper('salesrule')->__('No'),
            ),
        ));

        if(!$model->getId()){
            //set the default value for is_rss feed to yes for new promotion
            $model->setIsRss(1);
        }

        $form->setValues($model->getData());

        $autoGenerationCheckbox->setValue(1);

        if ($model->isReadonly()) {
            foreach ($fieldset->getElements() as $element) {
                $element->setReadonly(true, true);
            }
        }

        //$form->setUseContainer(true);

        $this->setForm($form);

        // field dependencies
        $this->setChild('form_after', $this->getLayout()->createBlock('adminhtml/widget_form_element_dependence')
            ->addFieldMap($couponTypeFiled->getHtmlId(), $couponTypeFiled->getName())
            ->addFieldMap($couponCodeFiled->getHtmlId(), $couponCodeFiled->getName())
            ->addFieldMap($autoGenerationCheckbox->getHtmlId(), $autoGenerationCheckbox->getName())
            ->addFieldMap($usesPerCouponFiled->getHtmlId(), $usesPerCouponFiled->getName())
            ->addFieldDependence(
                $couponCodeFiled->getName(),
                $couponTypeFiled->getName(),
                Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC)
            ->addFieldDependence(
                $autoGenerationCheckbox->getName(),
                $couponTypeFiled->getName(),
                Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC)
            ->addFieldDependence(
                $usesPerCouponFiled->getName(),
                $couponTypeFiled->getName(),
                Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC)
        );

        Mage::dispatchEvent('adminhtml_promo_quote_edit_tab_main_prepare_form', array('form' => $form));

        return Mage_Adminhtml_Block_Widget_Form::_prepareForm();
    }
}