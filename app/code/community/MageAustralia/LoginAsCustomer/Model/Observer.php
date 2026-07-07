<?php

declare(strict_types=1);

/**
 * Observers:
 *  - inject the admin "Login as Customer" button on the customer edit page;
 *  - on customer login, detect an admin-initiated impersonation (a recent
 *    "requested" audit row) and flag the session for the banner + log success.
 *
 * @category   MageAustralia
 * @package    MageAustralia_LoginAsCustomer
 * @copyright  Copyright (c) 2026 Mage Australia Pty Ltd
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class MageAustralia_LoginAsCustomer_Model_Observer
{
    /** How recent a "requested" row must be (seconds) to count as the matching impersonation. */
    protected int $_matchWindow = 900; // 15 min, comfortably covers core's 10-min magic-link expiry

    /**
     * Add a "Login as Customer" button to the admin customer edit screen.
     * Uses the public addButton() API on the container block, so no rewrite.
     */
    public function addLoginButton(\Maho\Event\Observer $observer): void
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
        // _setForcedFormKeyActions(), and an admin GET link must carry it.
        $url = $block->getUrl('adminhtml/logincustomer_index/create', [
            'id'       => $customerId,
            'form_key' => Mage::getSingleton('core/session')->getFormKey(),
        ]);

        $label = $helper->isMagicLinkEnabled()
            ? $helper->__('Login as Customer')
            : $helper->__('Login as Customer (enable Magic Link first)');

        $block->addButton('login_as_customer', [
            'label'   => $label,
            'onclick' => "confirm('"
                . $helper->__('Open a storefront session as this customer? This will be recorded in the audit log.')
                . "') && (window.open('" . $url . "', '_blank'))",
            'class'   => 'go',
        ], 0, 100);
    }

    /**
     * On customer login, if this customer has a recent admin-initiated
     * "requested" impersonation row (and no later success), treat the login as
     * an impersonation: flag the session for the banner and record success.
     *
     * Fires for every customer login but is a no-op for normal logins, since
     * only the admin button writes a "requested" row.
     */
    public function onCustomerLogin(\Maho\Event\Observer $observer): void
    {
        /** @var MageAustralia_LoginAsCustomer_Helper_Data $helper */
        $helper = Mage::helper('loginascustomer');
        if (!$helper->isEnabled()) {
            return;
        }

        $customer = $observer->getEvent()->getCustomer();
        if (!$customer || !$customer->getId()) {
            return;
        }
        $customerId = (int) $customer->getId();

        $pending = $this->_findPendingRequest($customerId);
        if ($pending === null) {
            return; // normal login, not an admin impersonation
        }

        // Flag the session so the storefront renders the impersonation banner.
        Mage::getSingleton('customer/session')->setData(
            MageAustralia_LoginAsCustomer_Helper_Data::SESSION_FLAG,
            [
                'admin_id'   => (int) $pending->getAdminId(),
                'admin'      => (string) $pending->getAdminUsername(),
                'started_at' => Mage_Core_Model_Locale::nowUtc(),
            ],
        );

        // Opt this session out of Full Page Cache for its whole lifetime. The
        // banner is per-session and must never be baked into a shared cached
        // page (PII leak) or be absent because a cached page was served. The
        // EXTERNAL_NO_CACHE cookie is honoured by maho-module-fpc (and the
        // shipped nginx map); harmless if FPC is not installed.
        $helper->setNoCacheCookie(true);

        $helper->log(
            $customerId,
            (int) $pending->getAdminId(),
            MageAustralia_LoginAsCustomer_Model_Log::STATUS_SUCCESS,
            (int) Mage::app()->getStore()->getId(),
            'Login as customer succeeded',
            (string) $customer->getEmail(),
            (string) $pending->getAdminUsername(),
        );
    }

    /**
     * On logout, clear the FPC no-cache cookie so the visitor's next anonymous
     * browsing can be served from cache again. (The session flag dies with the
     * session, so it needs no explicit clearing.)
     */
    public function onCustomerLogout(\Maho\Event\Observer $observer): void
    {
        /** @var MageAustralia_LoginAsCustomer_Helper_Data $helper */
        $helper = Mage::helper('loginascustomer');
        $helper->setNoCacheCookie(false);
    }

    /**
     * Most recent "requested" row for this customer within the match window
     * that has no later "success" row. Null if none (i.e. a normal login).
     */
    protected function _findPendingRequest(int $customerId): ?MageAustralia_LoginAsCustomer_Model_Log
    {
        $threshold = gmdate('Y-m-d H:i:s', time() - $this->_matchWindow);

        /** @var MageAustralia_LoginAsCustomer_Model_Resource_Log_Collection $collection */
        $collection = Mage::getResourceModel('loginascustomer/log_collection')
            ->addFieldToFilter('customer_id', $customerId)
            ->addFieldToFilter('created_at', ['gteq' => $threshold])
            ->setOrder('log_id', 'DESC');

        $requested = null;
        $latestSuccessId = 0;
        foreach ($collection as $row) {
            $status = $row->getStatus();
            if ($status === MageAustralia_LoginAsCustomer_Model_Log::STATUS_SUCCESS) {
                $latestSuccessId = max($latestSuccessId, (int) $row->getId());
            } elseif ($status === MageAustralia_LoginAsCustomer_Model_Log::STATUS_REQUESTED && $requested === null) {
                $requested = $row;
            }
        }

        // Only treat as pending if the newest requested row is newer than any success.
        if ($requested !== null && (int) $requested->getId() > $latestSuccessId) {
            return $requested;
        }

        return null;
    }
}
