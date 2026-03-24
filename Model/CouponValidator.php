<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Model;

use Magento\SalesRule\Model\ResourceModel\Coupon\CollectionFactory as CouponCollectionFactory;
use Magento\SalesRule\Model\Rule;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Quote\Api\Data\CartInterface;

class CouponValidator
{
    private const ALLOWED_CODES = ['DEAL5', 'DEAL10', 'DEAL15', 'DEAL20', 'DEAL25'];

    public function __construct(
        private readonly CouponCollectionFactory $couponCollectionFactory,
        private readonly StoreManagerInterface $storeManager
    ) {}

    public function getValidRuleByCode(CartInterface $quote, string $code): ?Rule
    {
        $code = strtoupper(trim($code));
        if (!in_array($code, self::ALLOWED_CODES, true)) {
            return null;
        }

        $coupon = $this->couponCollectionFactory->create()
            ->addFieldToFilter('code', $code)
            ->getFirstItem();

        if (!$coupon->getId() || !$coupon->getRuleId()) {
            return null;
        }

        $rule = $coupon->getRule();
        if (!$rule || !$rule->getIsActive()) {
            return null;
        }

        $websiteId = (int)$this->storeManager->getStore($quote->getStoreId())->getWebsiteId();
        $websiteIds = array_map('intval', (array)$rule->getWebsiteIds());

        if (!in_array($websiteId, $websiteIds, true)) {
            return null;
        }

        return $rule;
    }
}
