<?php

declare(strict_types=1);

/**
 * One-time, hashed, short-TTL login token.
 *
 * @category   MageAustralia
 * @package    MageAustralia_LoginAsCustomer
 * @copyright  Copyright (c) 2026 Mage Australia Pty Ltd
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * @method string getTokenHash()
 * @method $this setTokenHash(string $hash)
 * @method int getCustomerId()
 * @method $this setCustomerId(int $id)
 * @method int getAdminId()
 * @method $this setAdminId(int $id)
 * @method int getStoreId()
 * @method $this setStoreId(int $id)
 * @method string getCreatedAt()
 * @method $this setCreatedAt(string $date)
 * @method string getExpiresAt()
 * @method $this setExpiresAt(string $date)
 * @method string|null getConsumedAt()
 */
class MageAustralia_LoginAsCustomer_Model_Token extends Mage_Core_Model_Abstract
{
    protected $_eventPrefix = 'loginascustomer_token';

    protected function _construct(): void
    {
        $this->_init('loginascustomer/token');
    }

    /**
     * Atomically claim a token by its raw value.
     *
     * Hashes the supplied raw token, then performs a single conditional UPDATE
     * that marks the row consumed only if it is currently unconsumed and not
     * expired. The number of affected rows tells us whether WE were the ones
     * who claimed it, which makes the token genuinely single-use even under
     * concurrent requests (no check-then-act race).
     *
     * @return self|null the loaded, freshly-consumed token, or null if the
     *                   token was invalid, already used, or expired
     */
    public function consume(string $rawToken): ?self
    {
        $rawToken = trim($rawToken);
        if ($rawToken === '') {
            return null;
        }

        /** @var MageAustralia_LoginAsCustomer_Model_Resource_Token $resource */
        $resource = $this->getResource();
        $tokenId = $resource->claim($this->hashToken($rawToken));

        if (!$tokenId) {
            return null;
        }

        $this->load($tokenId);
        return $this->getId() ? $this : null;
    }

    /**
     * SHA-256 of the raw token. Only the hash is ever persisted, so a database
     * disclosure does not reveal usable login links.
     */
    public function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }
}
