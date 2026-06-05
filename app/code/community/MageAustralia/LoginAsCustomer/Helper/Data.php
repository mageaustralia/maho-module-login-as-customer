<?php

declare(strict_types=1);

/**
 * Login as Customer - business logic (stateless), per Maho helper convention.
 *
 * Security model:
 *  - Tokens are 256-bit CSPRNG values (random_bytes), URL-safe encoded.
 *  - Only the SHA-256 hash is stored; the raw token lives only in the
 *    one-time redirect URL. A DB read cannot mint a working link.
 *  - Tokens are single-use (atomic claim) and expire after a short TTL.
 *  - Every attempt is written to a permanent audit log.
 *
 * @category   MageAustralia
 * @package    MageAustralia_LoginAsCustomer
 * @copyright  Copyright (c) 2026 Mage Australia Pty Ltd
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class MageAustralia_LoginAsCustomer_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const XML_PATH_ENABLED     = 'loginascustomer/general/enabled';
    public const XML_PATH_TOKEN_TTL   = 'loginascustomer/general/token_ttl';
    public const XML_PATH_SHOW_BANNER = 'loginascustomer/general/show_banner';

    /** ACL resource gating the whole feature. */
    public const ACL_RESOURCE = 'admin/customer/loginascustomer';

    /** Customer-session flag set while an admin is impersonating. */
    public const SESSION_FLAG = 'mageaustralia_login_as_customer';

    protected int $_minTtl = 15;
    protected int $_maxTtl = 3600;

    public function isEnabled(?int $storeId = null): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ENABLED, $storeId);
    }

    public function isBannerEnabled(?int $storeId = null): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_SHOW_BANNER, $storeId);
    }

    /**
     * Token lifetime in seconds, clamped to a sane range so a mis-configured
     * value can never make tokens immortal or instantly dead.
     */
    public function getTokenTtl(?int $storeId = null): int
    {
        $ttl = (int) Mage::getStoreConfig(self::XML_PATH_TOKEN_TTL, $storeId);
        if ($ttl <= 0) {
            $ttl = 60;
        }
        return max($this->_minTtl, min($this->_maxTtl, $ttl));
    }

    /**
     * Whether the current admin is allowed to use the feature.
     */
    public function isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed(self::ACL_RESOURCE);
    }

    /**
     * Mint a one-time token for the given customer and return the raw token
     * (to be placed in the redirect URL). Persists only the hash.
     */
    public function createToken(int $customerId, int $adminId, int $storeId): string
    {
        $rawToken = $this->generateRawToken();

        /** @var MageAustralia_LoginAsCustomer_Model_Token $token */
        $token = Mage::getModel('loginascustomer/token');
        $ttl = $this->getTokenTtl($storeId);

        $token->setTokenHash($token->hashToken($rawToken))
            ->setCustomerId($customerId)
            ->setAdminId($adminId)
            ->setStoreId($storeId)
            ->setCreatedAt(Mage_Core_Model_Locale::nowUtc())
            // UTC, matching nowUtc(); the resource claim() compares against nowUtc().
            ->setExpiresAt(gmdate('Y-m-d H:i:s', time() + $ttl))
            ->save();

        // Opportunistic housekeeping so the table can't grow unbounded.
        try {
            $token->getResource()->purgeExpired();
        } catch (Exception $e) {
            Mage::logException($e);
        }

        return $rawToken;
    }

    /**
     * 256 bits of CSPRNG entropy, URL-safe base64 (no padding).
     */
    public function generateRawToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
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
