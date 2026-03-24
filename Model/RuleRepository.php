<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Model;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\SalesRule\Model\Coupon;
use Magento\SalesRule\Model\ResourceModel\Coupon\CollectionFactory as CouponCollectionFactory;
use Magento\SalesRule\Model\Rule;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Quote\Api\Data\CartInterface;

class RuleRepository
{
    public function __construct(
        private readonly CouponCollectionFactory $couponCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly TimezoneInterface $timezone,
        private readonly Config $config
    ) {
    }

    public function getRuleByCode(CartInterface $quote, string $code): ?Rule
    {
        $code = $this->config->normalizeCode($code);
        if (!$this->config->isAllowedCode($code)) {
            return null;
        }

        /** @var Coupon $coupon */
        $coupon = $this->couponCollectionFactory->create()
            ->addFieldToFilter('code', $code)
            ->setPageSize(1)
            ->getFirstItem();

        if (!$coupon->getId() || !$coupon->getRuleId()) {
            return null;
        }

        $rule = $coupon->getRule();
        if (!$rule || !$rule->getId() || !(bool)$rule->getIsActive()) {
            return null;
        }

        $websiteId = (int)$this->storeManager->getStore((int)$quote->getStoreId())->getWebsiteId();
        $ruleWebsiteIds = array_map('intval', (array)$rule->getWebsiteIds());
        if ($ruleWebsiteIds && !in_array($websiteId, $ruleWebsiteIds, true)) {
            return null;
        }

        $customerGroupId = (int)$quote->getCustomerGroupId();
        $ruleCustomerGroupIds = array_map('intval', (array)$rule->getCustomerGroupIds());
        if ($ruleCustomerGroupIds && !in_array($customerGroupId, $ruleCustomerGroupIds, true)) {
            return null;
        }

        $today = $this->timezone->date()->format('Y-m-d');
        $fromDate = (string)$rule->getFromDate();
        $toDate = (string)$rule->getToDate();
        if ($fromDate !== '' && $today < $fromDate) {
            return null;
        }
        if ($toDate !== '' && $today > $toDate) {
            return null;
        }

        return $rule;
    }
}
