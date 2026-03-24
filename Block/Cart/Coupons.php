<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Block\Cart;

use Magento\Framework\View\Element\Template;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Merlin\MultiCoupon\Model\Config;
use Merlin\MultiCoupon\Model\QuoteCouponStorage;

class Coupons extends Template
{
    /**
     * @param Template\Context $context
     * @param CheckoutSession $checkoutSession
     * @param QuoteCouponStorage $quoteCouponStorage
     * @param Config $config
     * @param array<string, mixed> $data
     */
    public function __construct(
        Template\Context $context,
        private readonly CheckoutSession $checkoutSession,
        private readonly QuoteCouponStorage $quoteCouponStorage,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Return the currently applied codes for the active cart.
     *
     * @return string[]
     */
    public function getAppliedCodes(): array
    {
        $quote = $this->checkoutSession->getQuote();
        if (!$quote || !$quote->getId()) {
            return [];
        }

        return $this->quoteCouponStorage->getCodes($quote);
    }

    /**
     * Return the allowed multi-coupon codes.
     *
     * @return string[]
     */
    public function getAllowedCodes(): array
    {
        return $this->config->getAllowedCodes();
    }

    /**
     * Return the add-coupon post URL.
     *
     * @return string
     */
    public function getAddUrl(): string
    {
        return $this->getUrl('multicoupon/cart/addCoupon');
    }

    /**
     * Return the remove-coupon post URL.
     *
     * @return string
     */
    public function getRemoveUrl(): string
    {
        return $this->getUrl('multicoupon/cart/removeCoupon');
    }

    /**
     * Return the clear-coupons post URL.
     *
     * @return string
     */
    public function getClearUrl(): string
    {
        return $this->getUrl('multicoupon/cart/clearCoupons');
    }
}
