<?php

declare(strict_types=1);

use Maho\Config\Route;

/**
 * Admin entry point: mint a core magic-link token for the customer and hand off
 * to core's storefront magicLinkLogin action.
 *
 * URL: adminhtml/logincustomer_index/create
 *
 * @category   MageAustralia
 * @package    MageAustralia_LoginAsCustomer
 * @copyright  Copyright (c) 2026 Mage Australia Pty Ltd
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class MageAustralia_LoginAsCustomer_Adminhtml_Logincustomer_IndexController extends Mage_Adminhtml_Controller_Action
{
    /**
     * CSRF: the create action changes state (mints a token + drives a login),
     * so it must carry a valid form key.
     */
    #[\Override]
    public function preDispatch()
    {
        $this->_setForcedFormKeyActions(['create']);
        return parent::preDispatch();
    }

    #[\Override]
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed(
            MageAustralia_LoginAsCustomer_Helper_Data::ACL_RESOURCE,
        );
    }

    /**
     * Mint a magic-link token for the requested customer and redirect the admin
     * to the storefront magicLinkLogin URL (core performs the actual login).
     */
    #[Route('/admin/logincustomer_index/create', methods: ['GET', 'POST'])]
    public function createAction(): void
    {
        /** @var MageAustralia_LoginAsCustomer_Helper_Data $helper */
        $helper = Mage::helper('loginascustomer');
        $session = Mage::getSingleton('adminhtml/session');

        if (!$helper->isEnabled()) {
            $session->addError($helper->__('Login as Customer is disabled.'));
            $this->_redirect('adminhtml/customer/index');
            return;
        }

        if (!$helper->isMagicLinkEnabled()) {
            $session->addError($helper->__('Magic Link login must be enabled (System > Configuration > Customers > Login Options) for Login as Customer to work.'));
            $this->_redirect('adminhtml/customer/index');
            return;
        }

        $customerId = (int) $this->getRequest()->getParam('id');
        $adminUser = Mage::getSingleton('admin/session')->getUser();
        $adminId = (int) $adminUser->getId();

        /** @var Mage_Customer_Model_Customer $customer */
        $customer = Mage::getModel('customer/customer')->load($customerId);

        if (!$customer->getId()) {
            $helper->log(
                $customerId,
                $adminId,
                MageAustralia_LoginAsCustomer_Model_Log::STATUS_FAILED,
                0,
                'Customer not found',
                null,
                $adminUser->getUsername(),
            );
            $session->addError($helper->__('Customer not found.'));
            $this->_redirect('adminhtml/customer/index');
            return;
        }

        if (!$customer->getIsActive()) {
            $helper->log(
                $customerId,
                $adminId,
                MageAustralia_LoginAsCustomer_Model_Log::STATUS_FAILED,
                0,
                'Customer account is inactive',
                $customer->getEmail(),
                $adminUser->getUsername(),
            );
            $session->addError($helper->__('Customer account is inactive.'));
            $this->_redirect('adminhtml/customer/edit', ['id' => $customerId]);
            return;
        }

        $storeId = $helper->resolveCustomerStoreId($customer);

        // Record the request BEFORE the redirect; the customer_login observer
        // matches this row to flag the session + record success.
        $helper->log(
            $customerId,
            $adminId,
            MageAustralia_LoginAsCustomer_Model_Log::STATUS_REQUESTED,
            $storeId,
            'Magic-link issued',
            $customer->getEmail(),
            $adminUser->getUsername(),
        );

        $url = $helper->createMagicLoginUrl($customer, $storeId);

        $this->getResponse()->setRedirect($url);
    }
}
