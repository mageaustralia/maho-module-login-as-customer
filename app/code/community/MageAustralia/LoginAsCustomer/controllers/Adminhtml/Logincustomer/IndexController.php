<?php

declare(strict_types=1);

/**
 * Admin entry point: mint a one-time login token and hand off to the storefront.
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
     * CSRF: the create action changes state (mints a token + logs in), so it
     * must carry a valid form key.
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
     * Create a one-time token for the requested customer and redirect the admin
     * to the storefront handover URL (which actually performs the login).
     */
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

        $storeId = $helper->resolveCustomerStoreId($customer);

        $rawToken = $helper->createToken($customerId, $adminId, $storeId);

        // Record the request now; the storefront leg records the actual login.
        $helper->log(
            $customerId,
            $adminId,
            MageAustralia_LoginAsCustomer_Model_Log::STATUS_REQUESTED,
            $storeId,
            'Token issued',
            $customer->getEmail(),
            $adminUser->getUsername(),
        );

        // Build the storefront handover URL on the customer's own store so the
        // session cookie is set on the right domain/scope.
        $store = Mage::app()->getStore($storeId);
        $url = $store->getUrl('loginascustomer/index/login', [
            '_secure' => true,
            '_nosid'  => true,
            'token'   => $rawToken,
        ]);

        $this->getResponse()->setRedirect($url);
    }
}
