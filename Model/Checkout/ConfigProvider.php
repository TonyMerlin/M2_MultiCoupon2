<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Model\Checkout;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Store\Model\StoreManagerInterface;
use Merlin\MultiCoupon\Model\Config;
use Merlin\MultiCoupon\Model\QuoteCouponStorage;

class ConfigProvider implements ConfigProviderInterface
{
    /**
     * @param CheckoutSession $checkoutSession
     * @param QuoteCouponStorage $quoteCouponStorage
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly QuoteCouponStorage $quoteCouponStorage,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager
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
        $storeId = $quote && $quote->getId()
            ? (int)$quote->getStoreId()
            : (int)$this->storeManager->getStore()->getId();

        if (!$this->config->isEnabled($storeId)) {
            return [
                'merlinMultiCoupon' => [
                    'enabled' => false,
                    'codes' => []
                ]
            ];
        }

        return [
            'merlinMultiCoupon' => [
                'enabled' => true,
                'codes' => $quote && $quote->getId()
                    ? $this->quoteCouponStorage->getCodes($quote)
                    : []
            ]
        ];
    }
}
