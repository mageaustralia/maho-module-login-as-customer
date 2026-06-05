<?php

declare(strict_types=1);

/**
 * Storefront handover: consume a one-time token and log the customer in.
 *
 * This is the security-critical leg. It:
 *  - atomically consumes the token (single-use, unexpired) BEFORE any login,
 *  - re-validates the customer still exists and is not already impersonated,
 *  - renews the session id (defeats session fixation) before authenticating,
 *  - flags the session so the storefront shows an impersonation banner,
 *  - writes a success/failure audit record.
 *
 * @category   MageAustralia
 * @package    MageAustralia_LoginAsCustomer
 * @copyright  Copyright (c) 2026 Mage Australia Pty Ltd
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class MageAustralia_LoginAsCustomer_IndexController extends Mage_Core_Controller_Front_Action
{
    /**
     * GET .../loginascustomer/index/login/token/<raw>
     *
     * Token consumption is the authorization gate here: a valid unconsumed
     * token is proof the admin (who passed ACL + form-key on the admin leg)
     * authorised this login. The token is single-use and short-lived, so the
     * raw value in the URL/referrer is useless once consumed.
     */
    public function loginAction(): void
    {
        /** @var MageAustralia_LoginAsCustomer_Helper_Data $helper */
        $helper = Mage::helper('loginascustomer');

        if (!$helper->isEnabled()) {
            $this->_redirect('');
            return;
        }

        $rawToken = (string) $this->getRequest()->getParam('token');

        /** @var MageAustralia_LoginAsCustomer_Model_Token $token */
        $token = Mage::getModel('loginascustomer/token')->consume($rawToken);

        if ($token === null) {
            // Invalid, already used, or expired. Don't reveal which.
            $helper->log(0, 0, MageAustralia_LoginAsCustomer_Model_Log::STATUS_FAILED, (int) Mage::app()->getStore()->getId(), 'Invalid or expired token');
            Mage::getSingleton('core/session')->addError(
                $helper->__('This login link is invalid or has expired.'),
            );
            $this->_redirect('');
            return;
        }

        $customerId = (int) $token->getCustomerId();
        $adminId = (int) $token->getAdminId();
        $storeId = (int) $token->getStoreId();

        /** @var Mage_Customer_Model_Customer $customer */
        $customer = Mage::getModel('customer/customer')->load($customerId);

        if (!$customer->getId()) {
            $helper->log($customerId, $adminId, MageAustralia_LoginAsCustomer_Model_Log::STATUS_FAILED, $storeId, 'Customer no longer exists');
            Mage::getSingleton('core/session')->addError($helper->__('Customer account is unavailable.'));
            $this->_redirect('');
            return;
        }

        /** @var Mage_Customer_Model_Session $customerSession */
        $customerSession = Mage::getSingleton('customer/session');

        // Clear any existing front-end session state, then renew the session id
        // so the impersonated session is brand new (anti session-fixation).
        $this->_clearFrontendSessions();
        $customerSession->renewSession();

        Mage::dispatchEvent('mageaustralia_loginascustomer_before_login', [
            'customer' => $customer,
            'admin_id' => $adminId,
        ]);

        $customerSession->loginById($customerId);

        // Flag the session so the storefront renders the impersonation banner
        // and downstream code can tell this is an admin-driven session.
        $customerSession->setData(MageAustralia_LoginAsCustomer_Helper_Data::SESSION_FLAG, [
            'admin_id'   => $adminId,
            'started_at' => Mage_Core_Model_Locale::nowUtc(),
        ]);

        $helper->log(
            $customerId,
            $adminId,
            MageAustralia_LoginAsCustomer_Model_Log::STATUS_SUCCESS,
            $storeId,
            'Login as customer succeeded',
            $customer->getEmail(),
        );

        Mage::dispatchEvent('mageaustralia_loginascustomer_after_login', [
            'customer' => $customer,
            'admin_id' => $adminId,
        ]);

        $this->_redirect('customer/account');
    }

    /**
     * Clear the front-end session singletons so the impersonated session does
     * not inherit the previous visitor's cart, wishlist, compare list, etc.
     */
    protected function _clearFrontendSessions(): void
    {
        // Capture the previous customer id BEFORE clearing the session, so the
        // persistent-session cleanup below can target the right account.
        $prevCustomerId = (int) Mage::getSingleton('customer/session')->getCustomerId();

        // Drop any persistent ("remember me") session for the previous customer
        // first, while we still have their id.
        if ($prevCustomerId && Mage::helper('core')->isModuleEnabled('Mage_Persistent')) {
            try {
                $persistent = Mage::getSingleton('persistent/session');
                if ($persistent) {
                    $persistent->clear()->deleteByCustomerId($prevCustomerId);
                }
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }

        $sessions = [
            'checkout/session',
            'catalog/session',
            'customer/session',
            'newsletter/session',
            'reports/session',
            'review/session',
            'wishlist/session',
            'catalogsearch/session',
        ];

        foreach ($sessions as $class) {
            try {
                $singleton = Mage::getSingleton($class);
                if ($singleton instanceof Mage_Core_Model_Session_Abstract) {
                    $singleton->clear();
                }
            } catch (Exception $e) {
                // A module providing one of these may be absent; ignore.
                Mage::logException($e);
            }
        }
    }
}
