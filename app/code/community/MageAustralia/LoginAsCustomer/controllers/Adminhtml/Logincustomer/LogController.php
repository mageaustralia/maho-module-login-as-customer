<?php

declare(strict_types=1);

/**
 * Admin audit-log grid controller.
 *
 * URL: adminhtml/logincustomer_log/index
 *
 * @category   MageAustralia
 * @package    MageAustralia_LoginAsCustomer
 * @copyright  Copyright (c) 2026 Mage Australia Pty Ltd
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class MageAustralia_LoginAsCustomer_Adminhtml_Logincustomer_LogController extends Mage_Adminhtml_Controller_Action
{
    #[\Override]
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('admin/customer/loginascustomer/log');
    }

    public function indexAction(): void
    {
        $this->loadLayout();
        $this->_setActiveMenu('customer/loginascustomer_log');
        $this->_addContent($this->getLayout()->createBlock('loginascustomer/adminhtml_log'));
        $this->renderLayout();
    }

    public function gridAction(): void
    {
        $this->loadLayout();
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('loginascustomer/adminhtml_log_grid')->toHtml(),
        );
    }
}
