<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Plugin\Checkout;

use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Quote\Api\Data\TotalsInterface;
use Magento\Quote\Model\QuoteRepository;
use Merlin\MultiCoupon\Model\QuoteCouponStorage;

class CartTotalRepositoryPlugin
{
    /**
     * @param QuoteRepository $quoteRepository
     * @param QuoteCouponStorage $quoteCouponStorage
     */
    public function __construct(
        private readonly QuoteRepository $quoteRepository,
        private readonly QuoteCouponStorage $quoteCouponStorage
    ) {
    }

    /**
     * Add applied multi-coupon codes as extension data on the returned cart totals object.
     *
     * @param CartTotalRepositoryInterface $subject
     * @param TotalsInterface $result
     * @param int|string $cartId
     * @return TotalsInterface
     */
    public function afterGet(CartTotalRepositoryInterface $subject, TotalsInterface $result, $cartId): TotalsInterface
    {
        $quote = $this->quoteRepository->getActive($cartId);
        $extensionAttributes = $result->getExtensionAttributes();
        if ($extensionAttributes) {
            $extensionAttributes->setMerlinMultiCouponCodes($this->quoteCouponStorage->getCodes($quote));
            $result->setExtensionAttributes($extensionAttributes);
        }

        return $result;
    }
}
