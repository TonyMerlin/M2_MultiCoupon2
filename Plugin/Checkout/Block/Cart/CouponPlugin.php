<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Plugin\Checkout\Block\Cart;

use Magento\Checkout\Block\Cart\Coupon;
use Merlin\MultiCoupon\Model\Config;

class CouponPlugin
{
    /**
     * @param Config $config
     */
    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * Hide Magento's native cart coupon block when Multi Coupon is enabled.
     *
     * @param Coupon $subject
     * @param string $result
     * @return string
     */
    public function afterToHtml(Coupon $subject, string $result): string
    {
        $storeId = (int)$subject->getStoreId();

        if ($this->config->isEnabled($storeId)) {
            return '';
        }

        return $result;
    }
}
