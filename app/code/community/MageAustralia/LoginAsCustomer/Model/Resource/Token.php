<?php

declare(strict_types=1);

/**
 * Token resource model.
 *
 * @category   MageAustralia
 * @package    MageAustralia_LoginAsCustomer
 * @copyright  Copyright (c) 2026 Mage Australia Pty Ltd
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class MageAustralia_LoginAsCustomer_Model_Resource_Token extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct(): void
    {
        $this->_init('loginascustomer/token', 'token_id');
    }

    /**
     * Atomically mark a single, valid, unconsumed, unexpired token as consumed.
     *
     * Returns the token_id only when THIS call flipped it (affected rows === 1),
     * which guarantees single use even under concurrent requests. Returns null
     * if the hash is unknown, already consumed, or expired.
     */
    public function claim(string $tokenHash): ?int
    {
        $adapter = $this->_getWriteAdapter();
        $table = $this->getMainTable();
        $now = Mage_Core_Model_Locale::nowUtc();

        // Resolve to the row id first (read), then conditionally claim it (write).
        // The WHERE on consumed_at IS NULL + expires_at >= now is what makes the
        // UPDATE the atomic gate; the prior SELECT only narrows to one row.
        $tokenId = $adapter->fetchOne(
            $adapter->select()
                ->from($table, 'token_id')
                ->where('token_hash = ?', $tokenHash)
                ->limit(1),
        );

        if (!$tokenId) {
            return null;
        }

        $affected = $adapter->update(
            $table,
            ['consumed_at' => $now],
            [
                'token_id = ?'       => (int) $tokenId,
                'consumed_at IS NULL',
                'expires_at >= ?'    => $now,
            ],
        );

        return $affected === 1 ? (int) $tokenId : null;
    }

    /**
     * Housekeeping: drop expired or consumed tokens. Safe to call opportunistically.
     */
    public function purgeExpired(): int
    {
        $adapter = $this->_getWriteAdapter();
        return (int) $adapter->delete(
            $this->getMainTable(),
            ['expires_at < ? OR consumed_at IS NOT NULL' => Mage_Core_Model_Locale::nowUtc()],
        );
    }
}
