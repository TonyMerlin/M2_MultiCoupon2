<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Block\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Template;
use Merlin\MultiCoupon\Model\Config;
use Merlin\MultiCoupon\Model\QuoteCouponStorage;

class Coupons extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly CheckoutSession $checkoutSession,
        private readonly QuoteCouponStorage $quoteCouponStorage,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getAppliedCodes(): array
    {
        $quote = $this->checkoutSession->getQuote();
        if (!$quote || !$quote->getId()) {
            return [];
        }

        return $this->quoteCouponStorage->getCodes($quote);
    }

    public function getAllowedCodes(): array
    {
        return $this->config->getAllowedCodes();
    }

    public function getAddUrl(): string
    {
        return $this->getUrl('multicoupon/cart/addCoupon');
    }

    public function getRemoveUrl(): string
    {
        return $this->getUrl('multicoupon/cart/removeCoupon');
    }

    public function getClearUrl(): string
    {
        return $this->getUrl('multicoupon/cart/clearCoupons');
    }
}
