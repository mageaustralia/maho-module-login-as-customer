<?php

declare(strict_types=1);

/**
 * Log resource model.
 *
 * @category   MageAustralia
 * @package    MageAustralia_LoginAsCustomer
 * @copyright  Copyright (c) 2026 Mage Australia Pty Ltd
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class MageAustralia_LoginAsCustomer_Model_Resource_Log extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct(): void
    {
        $this->_init('loginascustomer/log', 'log_id');
    }
}
