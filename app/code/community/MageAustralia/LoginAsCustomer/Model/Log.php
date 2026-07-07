<?php

declare(strict_types=1);

/**
 * Audit log entry for a Login as Customer attempt.
 *
 * @category   MageAustralia
 * @package    MageAustralia_LoginAsCustomer
 * @copyright  Copyright (c) 2026 Mage Australia Pty Ltd
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * @method int getCustomerId()
 * @method $this setCustomerId(int $id)
 * @method $this setCustomerEmail(?string $email)
 * @method int getAdminId()
 * @method $this setAdminId(int $id)
 * @method $this setAdminUsername(?string $username)
 * @method $this setStoreId(int $id)
 * @method string getStatus()
 * @method $this setStatus(string $status)
 * @method $this setIpAddress(?string $ip)
 * @method $this setUserAgent(?string $ua)
 * @method $this setNote(?string $note)
 * @method string getCreatedAt()
 * @method $this setCreatedAt(string $date)
 */
class MageAustralia_LoginAsCustomer_Model_Log extends Mage_Core_Model_Abstract
{
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_SUCCESS   = 'success';
    public const STATUS_FAILED    = 'failed';

    protected $_eventPrefix = 'loginascustomer_log';

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('loginascustomer/log');
    }
}
