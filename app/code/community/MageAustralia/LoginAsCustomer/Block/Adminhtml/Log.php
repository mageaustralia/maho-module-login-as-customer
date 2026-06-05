<?php

declare(strict_types=1);

/**
 * Audit log grid container.
 *
 * @category   MageAustralia
 * @package    MageAustralia_LoginAsCustomer
 * @copyright  Copyright (c) 2026 Mage Australia Pty Ltd
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class MageAustralia_LoginAsCustomer_Block_Adminhtml_Log extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_log';
        $this->_blockGroup = 'loginascustomer';
        $this->_headerText = Mage::helper('loginascustomer')->__('Login as Customer Log');
        parent::__construct();
        $this->_removeButton('add');
    }
}
