<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Model;

use Magento\Quote\Api\Data\CartInterface;
use Magento\SalesRule\Model\ResourceModel\Coupon\CollectionFactory as CouponCollectionFactory;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\RuleFactory;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class RuleRepository
{
    /**
     * @param CouponCollectionFactory $couponCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     * @param RuleFactory $ruleFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CouponCollectionFactory $couponCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly RuleFactory $ruleFactory,
        private readonly LoggerInterface $logger
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
        $originalCode = $code;
        $code = $this->config->normalizeCode($code);

        if ($code === '' || !$this->config->isAllowedCode($code)) {
            $this->logger->error('Merlin MultiCoupon rule lookup rejected by config', [
                'original_code' => $originalCode,
                'normalized_code' => $code
            ]);
            return null;
        }

        $coupon = $this->couponCollectionFactory->create()
            ->addFieldToFilter('code', $code)
            ->getFirstItem();

        if (!$coupon->getId() || !$coupon->getRuleId()) {
            $this->logger->error('Merlin MultiCoupon coupon not found', [
                'code' => $code
            ]);
            return null;
        }

        /** @var Rule $rule */
        $rule = $this->ruleFactory->create()->load((int)$coupon->getRuleId());

        if (!(int)$rule->getId()) {
            $this->logger->error('Merlin MultiCoupon rule failed to load by rule_id', [
                'code' => $code,
                'coupon_id' => $coupon->getId(),
                'coupon_rule_id' => $coupon->getRuleId()
            ]);
            return null;
        }

        if (!(bool)$rule->getIsActive()) {
            $this->logger->error('Merlin MultiCoupon rule inactive', [
                'code' => $code,
                'rule_id' => $rule->getId()
            ]);
            return null;
        }

        $websiteId = (int)$this->storeManager->getStore((int)$quote->getStoreId())->getWebsiteId();
        $websiteIds = array_map('intval', (array)$rule->getWebsiteIds());

        $this->logger->error('Merlin MultiCoupon repository website check', [
            'code' => $code,
            'rule_id' => $rule->getId(),
            'quote_store_id' => (int)$quote->getStoreId(),
            'quote_website_id' => $websiteId,
            'rule_website_ids' => $websiteIds
        ]);

        if ($websiteIds && !in_array($websiteId, $websiteIds, true)) {
            $this->logger->error('Merlin MultiCoupon rule rejected by website mismatch', [
                'code' => $code,
                'rule_id' => $rule->getId(),
                'quote_website_id' => $websiteId,
                'rule_website_ids' => $websiteIds
            ]);
            return null;
        }

        return $rule;
    }
}
