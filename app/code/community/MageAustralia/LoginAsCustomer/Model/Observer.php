<?php

declare(strict_types=1);

/**
 * Observers: inject the admin button, and the storefront impersonation banner.
 *
 * @category   MageAustralia
 * @package    MageAustralia_LoginAsCustomer
 * @copyright  Copyright (c) 2026 Mage Australia Pty Ltd
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class MageAustralia_LoginAsCustomer_Model_Observer
{
    /**
     * Add a "Login as Customer" button to the admin customer edit screen.
     * Uses the public addButton() API on the container block, so no rewrite.
     */
    public function addLoginButton(Varien_Event_Observer $observer): void
    {
        $block = $observer->getEvent()->getBlock();
        if (!($block instanceof Mage_Adminhtml_Block_Customer_Edit)) {
            return;
        }

        /** @var MageAustralia_LoginAsCustomer_Helper_Data $helper */
        $helper = Mage::helper('loginascustomer');
        if (!$helper->isEnabled() || !$helper->isAllowed()) {
            return;
        }

        $customerId = (int) $block->getCustomerId();
        if (!$customerId) {
            return;
        }

        // Include the form key: the create action is CSRF-protected via
        // _setForcedFormKeyActions(), and an admin GET link must carry it
        // (the admin secret key alone does not satisfy form-key validation).
        $url = $block->getUrl('adminhtml/logincustomer_index/create', [
            'id'       => $customerId,
            'form_key' => Mage::getSingleton('core/session')->getFormKey(),
        ]);

        $block->addButton('login_as_customer', [
            'label'   => $helper->__('Login as Customer'),
            'onclick' => "confirm('"
                . $helper->__('Open a storefront session as this customer? This will be recorded in the audit log.')
                . "') && (window.open('" . $url . "', '_blank'))",
            'class'   => 'go',
        ], 0, 100);
    }
}
