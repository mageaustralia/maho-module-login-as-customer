<?php

declare(strict_types=1);

/**
 * Storefront impersonation banner.
 *
 * Renders only when the current customer session is an admin impersonation
 * (the session flag set by the handover controller) and the banner is enabled.
 * Otherwise produces no output.
 *
 * @category   MageAustralia
 * @package    MageAustralia_LoginAsCustomer
 * @copyright  Copyright (c) 2026 Mage Australia Pty Ltd
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class MageAustralia_LoginAsCustomer_Block_Frontend_Banner extends Mage_Core_Block_Template
{
    public function isImpersonating(): bool
    {
        /** @var MageAustralia_LoginAsCustomer_Helper_Data $helper */
        $helper = Mage::helper('loginascustomer');
        if (!$helper->isEnabled() || !$helper->isBannerEnabled()) {
            return false;
        }

        return (bool) Mage::getSingleton('customer/session')
            ->getData(MageAustralia_LoginAsCustomer_Helper_Data::SESSION_FLAG);
    }

    public function getCustomerName(): string
    {
        $customer = Mage::getSingleton('customer/session')->getCustomer();
        return trim((string) $customer->getName());
    }

    public function getCustomerEmail(): string
    {
        return (string) Mage::getSingleton('customer/session')->getCustomer()->getEmail();
    }

    /**
     * URL that ends the impersonated session (standard customer logout).
     */
    public function getExitUrl(): string
    {
        return $this->getUrl('customer/account/logout');
    }

    #[\Override]
    protected function _toHtml()
    {
        if (!$this->isImpersonating()) {
            return '';
        }
        return parent::_toHtml();
    }
}
