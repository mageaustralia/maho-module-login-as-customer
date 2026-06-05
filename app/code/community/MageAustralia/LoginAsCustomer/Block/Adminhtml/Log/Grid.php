<?php

declare(strict_types=1);

/**
 * Audit log grid.
 *
 * @category   MageAustralia
 * @package    MageAustralia_LoginAsCustomer
 * @copyright  Copyright (c) 2026 Mage Australia Pty Ltd
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class MageAustralia_LoginAsCustomer_Block_Adminhtml_Log_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('loginascustomer_log_grid');
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }

    #[\Override]
    protected function _prepareCollection()
    {
        /** @var MageAustralia_LoginAsCustomer_Model_Resource_Log_Collection $collection */
        $collection = Mage::getResourceModel('loginascustomer/log_collection');
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $helper = Mage::helper('loginascustomer');

        $this->addColumn('created_at', [
            'header' => $helper->__('Date'),
            'index'  => 'created_at',
            'type'   => 'datetime',
            'width'  => '150px',
        ]);
        $this->addColumn('admin_username', [
            'header' => $helper->__('Admin'),
            'index'  => 'admin_username',
        ]);
        $this->addColumn('customer_id', [
            'header' => $helper->__('Customer ID'),
            'index'  => 'customer_id',
            'type'   => 'number',
            'width'  => '90px',
        ]);
        $this->addColumn('customer_email', [
            'header' => $helper->__('Customer Email'),
            'index'  => 'customer_email',
        ]);
        $this->addColumn('status', [
            'header'  => $helper->__('Status'),
            'index'   => 'status',
            'type'    => 'options',
            'width'   => '100px',
            'options' => [
                MageAustralia_LoginAsCustomer_Model_Log::STATUS_REQUESTED => $helper->__('Requested'),
                MageAustralia_LoginAsCustomer_Model_Log::STATUS_SUCCESS   => $helper->__('Success'),
                MageAustralia_LoginAsCustomer_Model_Log::STATUS_FAILED    => $helper->__('Failed'),
            ],
        ]);
        $this->addColumn('ip_address', [
            'header' => $helper->__('IP Address'),
            'index'  => 'ip_address',
            'width'  => '130px',
        ]);
        $this->addColumn('note', [
            'header' => $helper->__('Note'),
            'index'  => 'note',
        ]);

        return parent::_prepareColumns();
    }

    /**
     * Link each row to the customer edit page (a meaningful destination).
     */
    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('adminhtml/customer/edit', ['id' => $row->getCustomerId()]);
    }

    #[\Override]
    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', ['_current' => true]);
    }
}
