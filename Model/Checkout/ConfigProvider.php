<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Model\Checkout;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Merlin\MultiCoupon\Model\Config;
use Merlin\MultiCoupon\Model\QuoteCouponStorage;

class ConfigProvider implements ConfigProviderInterface
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly QuoteCouponStorage $quoteCouponStorage,
        private readonly Config $config
    ) {
    }

    public function getConfig(): array
    {
        $quote = $this->checkoutSession->getQuote();
        return [
            'merlinMultiCoupon' => [
                'allowedCodes' => $this->config->getAllowedCodes(),
                'codes' => $quote && $quote->getId() ? $this->quoteCouponStorage->getCodes($quote) : [],
                'addUrl' => 'multicoupon/cart/addCoupon',
                'removeUrl' => 'multicoupon/cart/removeCoupon',
                'clearUrl' => 'multicoupon/cart/clearCoupons'
            ]
        ];
    }
}
