<?php

declare(strict_types=1);

/**
 * MageAustralia_LoginAsCustomer install script.
 *
 * Creates:
 *  - token table: short-lived, single-use, HASHED login tokens
 *  - log table:   permanent audit trail of every impersonation attempt
 *
 * @category   MageAustralia
 * @package    MageAustralia_LoginAsCustomer
 * @copyright  Copyright (c) 2026 Mage Australia Pty Ltd
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();

/* -------------------------------------------------------------------------
 * Token table - one-time, hashed, short-TTL handover tokens.
 * The raw token NEVER touches the database; only its SHA-256 hash is stored,
 * so a DB read does not let an attacker mint a working login link.
 * ---------------------------------------------------------------------- */
$tokenTable = $installer->getTable('loginascustomer/token');
if (!$connection->isTableExists($tokenTable)) {
    $table = $connection->newTable($tokenTable)
        ->addColumn('token_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
            'identity'  => true,
            'unsigned'  => true,
            'nullable'  => false,
            'primary'   => true,
        ], 'Token ID')
        ->addColumn('token_hash', Varien_Db_Ddl_Table::TYPE_VARCHAR, 64, [
            'nullable'  => false,
        ], 'SHA-256 hash of the one-time token')
        ->addColumn('customer_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
            'unsigned'  => true,
            'nullable'  => false,
        ], 'Target Customer ID')
        ->addColumn('admin_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
            'unsigned'  => true,
            'nullable'  => false,
        ], 'Admin user who created the token')
        ->addColumn('store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, [
            'unsigned'  => true,
            'nullable'  => false,
            'default'   => 0,
        ], 'Store the login was scoped to')
        ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, [
            'nullable'  => false,
        ], 'Created At')
        ->addColumn('expires_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, [
            'nullable'  => false,
        ], 'Expires At')
        ->addColumn('consumed_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, [
            'nullable'  => true,
            'default'   => null,
        ], 'Consumed At (single-use marker)')
        ->addIndex(
            $installer->getIdxName(
                'loginascustomer/token',
                ['token_hash'],
                Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE,
            ),
            ['token_hash'],
            ['type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE],
        )
        ->addIndex(
            $installer->getIdxName('loginascustomer/token', ['expires_at']),
            ['expires_at'],
        )
        ->setComment('Login as Customer - one-time hashed handover tokens');
    $connection->createTable($table);
}

/* -------------------------------------------------------------------------
 * Audit log - permanent record of every impersonation, always written.
 * ---------------------------------------------------------------------- */
$logTable = $installer->getTable('loginascustomer/log');
if (!$connection->isTableExists($logTable)) {
    $table = $connection->newTable($logTable)
        ->addColumn('log_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
            'identity'  => true,
            'unsigned'  => true,
            'nullable'  => false,
            'primary'   => true,
        ], 'Log ID')
        ->addColumn('customer_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
            'unsigned'  => true,
            'nullable'  => false,
        ], 'Target Customer ID')
        ->addColumn('customer_email', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
            'nullable'  => true,
        ], 'Target Customer Email (snapshot)')
        ->addColumn('admin_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
            'unsigned'  => true,
            'nullable'  => false,
        ], 'Admin user ID')
        ->addColumn('admin_username', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
            'nullable'  => true,
        ], 'Admin username (snapshot)')
        ->addColumn('store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, [
            'unsigned'  => true,
            'nullable'  => false,
            'default'   => 0,
        ], 'Store ID')
        ->addColumn('status', Varien_Db_Ddl_Table::TYPE_VARCHAR, 20, [
            'nullable'  => false,
        ], 'Outcome: requested / success / failed')
        ->addColumn('ip_address', Varien_Db_Ddl_Table::TYPE_VARCHAR, 45, [
            'nullable'  => true,
        ], 'Admin IP address')
        ->addColumn('user_agent', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
            'nullable'  => true,
        ], 'Admin user agent')
        ->addColumn('note', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
            'nullable'  => true,
        ], 'Detail / failure reason')
        ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, [
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
