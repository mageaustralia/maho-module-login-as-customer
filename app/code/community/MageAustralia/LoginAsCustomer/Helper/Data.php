<?php

declare(strict_types=1);

/**
 * Login as Customer - business logic (stateless), per Maho helper convention.
 *
 * Login is delegated to Maho core's magic-link mechanism: we mint a magic-link
 * token on the customer (core's secure rp_token, with core's expiry + one-time
 * use + timing-safe validation) and hand off to core's
 * customer/account/magicLinkLogin action, which establishes the session through
 * the same proven path as a normal login. This module's own value-add is the
 * admin button (ACL + CSRF), the permanent audit trail, and the storefront
 * impersonation banner.
 *
 * @category   MageAustralia
 * @package    MageAustralia_LoginAsCustomer
 * @copyright  Copyright (c) 2026 Mage Australia Pty Ltd
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class MageAustralia_LoginAsCustomer_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const XML_PATH_ENABLED     = 'loginascustomer/general/enabled';
    public const XML_PATH_SHOW_BANNER = 'loginascustomer/general/show_banner';

    /** Core flag that must be on for the magic-link login action to exist. */
    public const XML_PATH_MAGIC_LINK_ENABLED = 'customer/login/magic_link_enabled';

    /** ACL resource gating the whole feature. */
    public const ACL_RESOURCE = 'admin/customer/loginascustomer';

    /** Customer-session flag set while an admin is impersonating. */
    public const SESSION_FLAG = 'mageaustralia_login_as_customer';

    public function isEnabled(?int $storeId = null): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ENABLED, $storeId);
    }

    public function isBannerEnabled(?int $storeId = null): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_SHOW_BANNER, $storeId);
    }

    /**
     * Whether Maho core's magic-link login is enabled. The whole feature
     * depends on it, since the login handoff uses core's magicLinkLogin action.
     */
    public function isMagicLinkEnabled(?int $storeId = null): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_MAGIC_LINK_ENABLED, $storeId);
    }

    /**
     * Whether the current admin is allowed to use the feature.
     */
    public function isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed(self::ACL_RESOURCE);
    }

    /**
     * Mint a magic-link token on the customer (core API) and return the
     * storefront login URL to redirect the admin to.
     *
     * Reuses core's rp_token: secure generation, one-time use (cleared by the
     * magicLinkLogin action), and core's configured expiry
     * (customer/login/magic_link_token_expiration, default 10 min).
     */
    public function createMagicLoginUrl(Mage_Customer_Model_Customer $customer, int $storeId): string
    {
        $token = $customer->generateMagicLinkToken();
        $customer->changeResetPasswordLinkToken($token);

        $store = Mage::app()->getStore($storeId);

        return $store->getUrl('customer/account/magicLinkLogin', [
            '_secure' => true,
            '_nosid'  => true,
            'token'   => $token,
        ]);
    }

    /**
     * Write an audit record. Never throws into the caller's flow.
     */
    public function log(
        int $customerId,
        int $adminId,
        string $status,
        int $storeId = 0,
        ?string $note = null,
        ?string $customerEmail = null,
        ?string $adminUsername = null,
    ): void {
        try {
            $request = Mage::app()->getRequest();
            $ua = (string) $request->getServer('HTTP_USER_AGENT', '');

            Mage::getModel('loginascustomer/log')
                ->setCustomerId($customerId)
                ->setCustomerEmail($customerEmail)
                ->setAdminId($adminId)
                ->setAdminUsername($adminUsername)
                ->setStoreId($storeId)
                ->setStatus($status)
                ->setIpAddress($this->getRemoteAddr())
                ->setUserAgent($ua !== '' ? substr($ua, 0, 255) : null)
                ->setNote($note !== null ? substr($note, 0, 255) : null)
                ->setCreatedAt(Mage_Core_Model_Locale::nowUtc())
                ->save();
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    /**
     * Real client IP, honouring Maho's proxy-aware remote address helper.
     */
    public function getRemoteAddr(): ?string
    {
        $ip = Mage::helper('core/http')->getRemoteAddr();
        return $ip !== false && $ip !== '' ? (string) $ip : null;
    }

    /**
     * Pick a valid, active store view for the customer to land on, mirroring
     * core account-share scope rules. Falls back to the default store view.
     */
    public function resolveCustomerStoreId(Mage_Customer_Model_Customer $customer): int
    {
        $storeId = (int) $customer->getStoreId();
        if ($storeId) {
            $store = Mage::app()->getStore($storeId);
            if ($store->getId() && $store->getIsActive()) {
                return $storeId;
            }
        }

        $websiteId = (int) $customer->getWebsiteId();
        if ($websiteId) {
            foreach (Mage::app()->getWebsite($websiteId)->getStores() as $store) {
                if ($store->getIsActive()) {
                    return (int) $store->getId();
                }
            }
        }

        return (int) Mage::app()->getDefaultStoreView()->getId();
    }
}
