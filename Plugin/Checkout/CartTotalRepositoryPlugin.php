<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Plugin\Checkout;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Quote\Api\Data\TotalsExtensionFactory;
use Magento\Quote\Api\Data\TotalsInterface;
use Merlin\MultiCoupon\Model\QuoteCouponStorage;

class CartTotalRepositoryPlugin
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly TotalsExtensionFactory $totalsExtensionFactory,
        private readonly QuoteCouponStorage $quoteCouponStorage
    ) {
    }

    public function afterGet(CartTotalRepositoryInterface $subject, TotalsInterface $result, $cartId): TotalsInterface
    {
        $quote = $this->cartRepository->get((int)$cartId);
        $extensionAttributes = $result->getExtensionAttributes() ?: $this->totalsExtensionFactory->create();
        $extensionAttributes->setMerlinMultiCouponCodes($this->quoteCouponStorage->getCodes($quote));
        $result->setExtensionAttributes($extensionAttributes);

        return $result;
    }
}
