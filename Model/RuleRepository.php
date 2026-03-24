<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Model;

use Magento\Quote\Api\Data\CartInterface;
use Magento\SalesRule\Model\ResourceModel\Coupon\CollectionFactory as CouponCollectionFactory;
use Magento\SalesRule\Model\Rule;
use Magento\Store\Model\StoreManagerInterface;

class RuleRepository
{
    /**
     * @param CouponCollectionFactory $couponCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     */
    public function __construct(
        private readonly CouponCollectionFactory $couponCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config
    ) {
    }

    /**
     * Load the active sales rule mapped to a coupon code for the current quote.
     *
     * @param CartInterface $quote
     * @param string $code
     * @return Rule|null
     */
    public function getRuleByCode(CartInterface $quote, string $code): ?Rule
    {
        $code = $this->config->normalizeCode($code);
        if (!$this->config->isAllowedCode($code)) {
            return null;
        }

        $coupon = $this->couponCollectionFactory->create()
            ->addFieldToFilter('code', $code)
            ->getFirstItem();

        if (!$coupon->getId() || !$coupon->getRuleId()) {
            return null;
        }

        $rule = $coupon->getRule();
        if (!$rule || !(bool)$rule->getIsActive()) {
            return null;
        }

        $websiteId = (int)$this->storeManager->getStore((int)$quote->getStoreId())->getWebsiteId();
        $websiteIds = array_map('intval', (array)$rule->getWebsiteIds());

        if (!in_array($websiteId, $websiteIds, true)) {
            return null;
        }

        return $rule;
    }
}
