<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Model\Checkout;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Merlin\MultiCoupon\Model\QuoteCouponStorage;

class ConfigProvider implements ConfigProviderInterface
{
    /**
     * @param CheckoutSession $checkoutSession
     * @param QuoteCouponStorage $quoteCouponStorage
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly QuoteCouponStorage $quoteCouponStorage
    ) {
    }

    /**
     * Return checkout config values used by the frontend multi-coupon component.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        $quote = $this->checkoutSession->getQuote();

        return [
            'merlinMultiCoupon' => [
                'codes' => $quote && $quote->getId() ? $this->quoteCouponStorage->getCodes($quote) : []
            ]
        ];
    }
}
