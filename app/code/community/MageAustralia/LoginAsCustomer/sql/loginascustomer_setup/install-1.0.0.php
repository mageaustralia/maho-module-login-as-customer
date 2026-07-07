<?php

declare(strict_types=1);

/**
 * MageAustralia_LoginAsCustomer install script.
 *
 * Creates the audit log table. Login itself is delegated to Maho core's
 * magic-link mechanism (rp_token + customer/account/magicLinkLogin), so this
 * module stores no login tokens of its own.
 *
 * @category   MageAustralia
 * @package    MageAustralia_LoginAsCustomer
 * @copyright  Copyright (c) 2026 Mage Australia Pty Ltd
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();

/* -------------------------------------------------------------------------
 * Audit log - permanent record of every impersonation, always written.
 * ---------------------------------------------------------------------- */
$logTable = $installer->getTable('loginascustomer/log');
if (!$connection->isTableExists($logTable)) {
    $table = $connection->newTable($logTable)
        ->addColumn('log_id', \Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'identity'  => true,
            'unsigned'  => true,
            'nullable'  => false,
            'primary'   => true,
        ], 'Log ID')
        ->addColumn('customer_id', \Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'unsigned'  => true,
            'nullable'  => false,
        ], 'Target Customer ID')
        ->addColumn('customer_email', \Maho\Db\Ddl\Table::TYPE_VARCHAR, 255, [
            'nullable'  => true,
        ], 'Target Customer Email (snapshot)')
        ->addColumn('admin_id', \Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'unsigned'  => true,
            'nullable'  => false,
        ], 'Admin user ID')
        ->addColumn('admin_username', \Maho\Db\Ddl\Table::TYPE_VARCHAR, 255, [
            'nullable'  => true,
        ], 'Admin username (snapshot)')
        ->addColumn('store_id', \Maho\Db\Ddl\Table::TYPE_SMALLINT, null, [
            'unsigned'  => true,
            'nullable'  => false,
            'default'   => 0,
        ], 'Store ID')
        ->addColumn('status', \Maho\Db\Ddl\Table::TYPE_VARCHAR, 20, [
            'nullable'  => false,
        ], 'Outcome: requested / success / failed')
        ->addColumn('ip_address', \Maho\Db\Ddl\Table::TYPE_VARCHAR, 45, [
            'nullable'  => true,
        ], 'Admin IP address')
        ->addColumn('user_agent', \Maho\Db\Ddl\Table::TYPE_VARCHAR, 255, [
            'nullable'  => true,
        ], 'Admin user agent')
        ->addColumn('note', \Maho\Db\Ddl\Table::TYPE_VARCHAR, 255, [
            'nullable'  => true,
        ], 'Detail / failure reason')
        ->addColumn('created_at', \Maho\Db\Ddl\Table::TYPE_DATETIME, null, [
            'nullable'  => false,
        ], 'Created At')
        ->addIndex(
            $installer->getIdxName('loginascustomer/log', ['customer_id']),
            ['customer_id'],
        )
        ->addIndex(
            $installer->getIdxName('loginascustomer/log', ['admin_id']),
            ['admin_id'],
        )
        ->addIndex(
            $installer->getIdxName('loginascustomer/log', ['created_at']),
            ['created_at'],
        )
        ->setComment('Login as Customer - audit log');
    $connection->createTable($table);
}

$installer->endSetup();
