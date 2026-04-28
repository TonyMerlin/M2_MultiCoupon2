<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Model\Discount;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Merlin\MultiCoupon\Model\Config;
use Merlin\MultiCoupon\Model\QuoteCouponStorage;
use Merlin\MultiCoupon\Model\RuleRepository;
use Psr\Log\LoggerInterface;

/**
 * Custom quote total for Merlin multi-coupon discounts.
 */
class MultiCoupon extends AbstractTotal
{
    /**
     * @param QuoteCouponStorage $quoteCouponStorage
     * @param RuleRepository $ruleRepository
     * @param ItemRuleMatcher $itemRuleMatcher
     * @param Calculator $calculator
     * @param PriceCurrencyInterface $priceCurrency
     * @param LoggerInterface $logger
     * @param Config $config
     */
    public function __construct(
        private readonly QuoteCouponStorage $quoteCouponStorage,
        private readonly RuleRepository $ruleRepository,
        private readonly ItemRuleMatcher $itemRuleMatcher,
        private readonly Calculator $calculator,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly LoggerInterface $logger,
        private readonly Config $config
    ) {
        $this->setCode('merlin_multi_coupon_discount');
    }

    /**
     * Collect and apply the best matching allowed coupon discount per item.
     *
     * @param Quote $quote
     * @param ShippingAssignmentInterface $shippingAssignment
     * @param Total $total
     * @return $this
     */
    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ): self {
        parent::collect($quote, $shippingAssignment, $total);

        if (!$this->config->isEnabled((int)$quote->getStoreId())) {
            return $this;
        }

        $items = $shippingAssignment->getItems();

        if (!$items || !$quote->getItemsCount()) {
            return $this;
        }

        $codes = $this->quoteCouponStorage->getCodes($quote);
        if (!$codes) {
            return $this;
        }

        $codes = $this->getRetainedCodesForItems($quote, $items, $codes);
        $quote->setData(QuoteCouponStorage::FIELD, implode(',', $codes));

        if (!$codes) {
            $quote->setAppliedRuleIds('');
            return $this;
        }

        $this->resetAddressTotals($quote, $shippingAssignment, $total, $items);

        $appliedCodes = [];
        $appliedRuleIds = [];
        $baseDiscountTotal = 0.0;
        $discountTotal = 0.0;

        foreach ($items as $item) {
            if ($item->getParentItem()) {
                continue;
            }

            $best = $this->getBestDiscountForItem($quote, $item, $codes);

            if ($best['base_amount'] <= 0.0001) {
                continue;
            }

            $baseAmount = $this->priceCurrency->round((float)$best['base_amount']);
            $amount = $this->priceCurrency->round((float)$best['amount']);

            $item->setBaseDiscountAmount($baseAmount);
            $item->setDiscountAmount($amount);
            $item->setBaseOriginalDiscountAmount($baseAmount);
            $item->setOriginalDiscountAmount($amount);

            $baseDiscountTotal += $baseAmount;
            $discountTotal += $amount;

            if ($best['code'] !== '') {
                $appliedCodes[$best['code']] = $best['code'];
            }
            if ($best['rule_id'] > 0) {
                $appliedRuleIds[$best['rule_id']] = $best['rule_id'];
            }
        }

        if ($baseDiscountTotal <= 0.0001 && $discountTotal <= 0.0001) {
            $quote->setAppliedRuleIds('');
            return $this;
        }

        $shipping = $shippingAssignment->getShipping();
        $address = $shipping ? $shipping->getAddress() : null;

        $total->setTotalAmount($this->getCode(), -$discountTotal);
        $total->setBaseTotalAmount($this->getCode(), -$baseDiscountTotal);

        $total->setDiscountAmount((float)$total->getDiscountAmount() - $discountTotal);
        $total->setBaseDiscountAmount((float)$total->getBaseDiscountAmount() - $baseDiscountTotal);

        if ($address instanceof Address) {
            $description = implode(', ', array_values($appliedCodes));
            $address->setDiscountDescription($description);
            $address->setDiscountAmount((float)$address->getDiscountAmount() - $discountTotal);
            $address->setBaseDiscountAmount((float)$address->getBaseDiscountAmount() - $baseDiscountTotal);
        }

        $quote->setData(QuoteCouponStorage::FIELD, implode(',', $codes));
        $quote->setAppliedRuleIds(implode(',', array_map('strval', array_values($appliedRuleIds))));

        return $this;
    }

    /**
     * Return the total row metadata for the multi-coupon discount.
     *
     * @param Quote $quote
     * @param Total $total
     * @return array<string, mixed>
     */
    public function fetch(Quote $quote, Total $total): array
    {
        $codes = $this->quoteCouponStorage->getCodes($quote);
        $discountAmount = (float)$total->getTotalAmount($this->getCode());

        if (!$codes || abs($discountAmount) < 0.0001) {
            return [];
        }

        return [
            'code' => $this->getCode(),
            'title' => __('Discount (%1)', implode(', ', $codes)),
            'value' => $discountAmount,
        ];
    }

    /**
     * Reset this module's custom discount amounts before re-collecting.
     *
     * @param Quote $quote
     * @param ShippingAssignmentInterface $shippingAssignment
     * @param Total $total
     * @param AbstractItem[] $items
     * @return void
     */
    private function resetAddressTotals(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total,
        array $items
    ): void {
        $total->setTotalAmount($this->getCode(), 0.0);
        $total->setBaseTotalAmount($this->getCode(), 0.0);

        foreach ($items as $item) {
            if ($item->getParentItem()) {
                continue;
            }

            $item->setDiscountAmount(0.0);
            $item->setBaseDiscountAmount(0.0);
            $item->setOriginalDiscountAmount(0.0);
            $item->setBaseOriginalDiscountAmount(0.0);
        }

        $shipping = $shippingAssignment->getShipping();
        $address = $shipping ? $shipping->getAddress() : null;
        if ($address instanceof Address) {
            $address->setDiscountAmount(0.0);
            $address->setBaseDiscountAmount(0.0);
            $address->setDiscountDescription(null);
        }
    }

    /**
     * Retain only codes that are currently the winning code for at least one item.
     *
     * This matches the actual pricing engine behaviour and prevents stale overlap
     * codes from lingering after basket changes.
     *
     * @param Quote $quote
     * @param AbstractItem[] $items
     * @param string[] $codes
     * @return string[]
     */
    private function getRetainedCodesForItems(Quote $quote, array $items, array $codes): array
    {
        $retained = [];

        foreach ($items as $item) {
            if ($item->getParentItem()) {
                continue;
            }

            $best = $this->getBestDiscountForItem($quote, $item, $codes);

            if ($best['code'] !== '') {
                $retained[$best['code']] = $best['code'];
            }
        }

        $ordered = [];
        foreach ($codes as $code) {
            if (isset($retained[$code])) {
                $ordered[] = $code;
            }
        }

        return array_values(array_unique($ordered));
    }

    /**
     * Return the best applicable discount result for a single quote item.
     *
     * OFFER codes take precedence over DEAL codes on the same item.
     * Within the same code class, the higher discount wins.
     *
     * @param Quote $quote
     * @param AbstractItem $item
     * @param string[] $codes
     * @return array{code:string,rule_id:int,base_amount:float,amount:float}
     */
    private function getBestDiscountForItem(Quote $quote, AbstractItem $item, array $codes): array
    {
        $best = [
            'code' => '',
            'rule_id' => 0,
            'base_amount' => 0.0,
            'amount' => 0.0,
            'priority' => -1,
        ];

        $storeBaseToQuoteRate = (float)$quote->getBaseToQuoteRate() ?: 1.0;

        foreach ($codes as $code) {
            $rule = $this->ruleRepository->getRuleByCode($quote, $code);

            if (!$rule) {
                continue;
            }

            if (!$this->itemRuleMatcher->isMatch($item, $rule)) {
                continue;
            }

            $baseAmount = $this->calculator->calculate($item, $rule);
            if ($baseAmount <= 0.0001) {
                continue;
            }

            $priority = str_starts_with($code, 'OFFER-') ? 1 : 0;

            $isBetter =
                $priority > $best['priority']
                || ($priority === $best['priority'] && $baseAmount > $best['base_amount']);

            if (!$isBetter) {
                continue;
            }

            $best = [
                'code' => $code,
                'rule_id' => (int)$rule->getId(),
                'base_amount' => $baseAmount,
                'amount' => $baseAmount * $storeBaseToQuoteRate,
                'priority' => $priority,
            ];
        }

        return [
            'code' => $best['code'],
            'rule_id' => $best['rule_id'],
            'base_amount' => $best['base_amount'],
            'amount' => $best['amount'],
        ];
    }
}
