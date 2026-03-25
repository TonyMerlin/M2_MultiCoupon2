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
     */
    public function __construct(
        private readonly QuoteCouponStorage $quoteCouponStorage,
        private readonly RuleRepository $ruleRepository,
        private readonly ItemRuleMatcher $itemRuleMatcher,
        private readonly Calculator $calculator,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly LoggerInterface $logger
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

        $items = $shippingAssignment->getItems();

        $this->logger->critical('Merlin MultiCoupon collect start', [
            'quote_id' => $quote->getId(),
            'codes' => $this->quoteCouponStorage->getCodes($quote),
            'items_count' => is_array($items) ? count($items) : 0,
            'grand_total_before' => $total->getGrandTotal(),
            'base_grand_total_before' => $total->getBaseGrandTotal()
        ]);

        if (!$items || !$quote->getItemsCount()) {
            $this->logger->critical('Merlin MultiCoupon collect aborted: no items', [
                'quote_id' => $quote->getId(),
                'quote_items_count' => $quote->getItemsCount()
            ]);
            return $this;
        }

        $codes = $this->quoteCouponStorage->getCodes($quote);
        if (!$codes) {
            $this->logger->critical('Merlin MultiCoupon collect aborted: no codes on quote', [
                'quote_id' => $quote->getId()
            ]);
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

            $this->logger->critical('Merlin MultiCoupon item loop', [
                'quote_id' => $quote->getId(),
                'item_id' => $item->getId(),
                'sku' => $item->getSku(),
                'qty' => $item->getQty(),
                'base_row_total' => $item->getBaseRowTotal(),
                'base_row_total_incl_tax' => $item->getBaseRowTotalInclTax(),
                'row_total' => $item->getRowTotal(),
                'row_total_incl_tax' => $item->getRowTotalInclTax()
            ]);

            $best = $this->getBestDiscountForItem($quote, $item, $codes);

            $this->logger->critical('Merlin MultiCoupon best result for item', [
                'quote_id' => $quote->getId(),
                'item_id' => $item->getId(),
                'sku' => $item->getSku(),
                'best' => $best
            ]);

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

        $this->logger->critical('Merlin MultiCoupon totals after item loop', [
            'quote_id' => $quote->getId(),
            'base_discount_total' => $baseDiscountTotal,
            'discount_total' => $discountTotal,
            'applied_codes' => array_values($appliedCodes),
            'applied_rule_ids' => array_values($appliedRuleIds)
        ]);

        if ($baseDiscountTotal <= 0.0001 && $discountTotal <= 0.0001) {
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

        $existingRuleIds = trim((string)$quote->getAppliedRuleIds());
        $mergedRuleIds = array_filter(array_unique(array_merge(
            $existingRuleIds !== '' ? explode(',', $existingRuleIds) : [],
            array_map('strval', array_values($appliedRuleIds))
        )));
        $quote->setAppliedRuleIds(implode(',', $mergedRuleIds));

        $this->logger->critical('Merlin MultiCoupon collect applied totals', [
            'quote_id' => $quote->getId(),
            'total_code' => $this->getCode(),
            'discount_total_amount' => $total->getTotalAmount($this->getCode()),
            'base_discount_total_amount' => $total->getBaseTotalAmount($this->getCode()),
            'discount_amount_field' => $total->getDiscountAmount(),
            'base_discount_amount_field' => $total->getBaseDiscountAmount(),
            'quote_applied_rule_ids' => $quote->getAppliedRuleIds()
        ]);

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

        $this->logger->critical('Merlin MultiCoupon fetch', [
            'quote_id' => $quote->getId(),
            'codes' => $codes,
            'discount_amount' => $discountAmount
        ]);

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

        $shipping = $shippingAssignment->getShipping();
        $address = $shipping ? $shipping->getAddress() : null;
        if ($address instanceof Address) {
            $address->setDiscountDescription(null);
        }
    }

    /**
     * Return the best applicable discount result for a single quote item.
     *
     * @param Quote $quote
     * @param AbstractItem $item
     * @param string[] $codes
     * @return array{code:string,rule_id:int,base_amount:float,amount:float}
     */
    private function getBestDiscountForItem(Quote $quote, AbstractItem $item, array $codes): array
    {
        $best = ['code' => '', 'rule_id' => 0, 'base_amount' => 0.0, 'amount' => 0.0];
        $storeBaseToQuoteRate = (float)$quote->getBaseToQuoteRate() ?: 1.0;

        foreach ($codes as $code) {
            $rule = $this->ruleRepository->getRuleByCode($quote, $code);

            $this->logger->critical('Merlin MultiCoupon rule lookup', [
                'quote_id' => $quote->getId(),
                'item_id' => $item->getId(),
                'sku' => $item->getSku(),
                'code' => $code,
                'rule_found' => (bool)$rule,
                'rule_id' => $rule ? $rule->getId() : null,
                'simple_action' => $rule ? $rule->getSimpleAction() : null,
                'discount_amount' => $rule ? $rule->getDiscountAmount() : null
            ]);

            if (!$rule) {
                continue;
            }

            $matched = $this->itemRuleMatcher->isMatch($item, $rule);

            $this->logger->critical('Merlin MultiCoupon match result', [
                'quote_id' => $quote->getId(),
                'item_id' => $item->getId(),
                'sku' => $item->getSku(),
                'code' => $code,
                'rule_id' => (int)$rule->getId(),
                'matched' => $matched
            ]);

            if (!$matched) {
                continue;
            }

            $baseAmount = $this->calculator->calculate($item, $rule);

            $this->logger->critical('Merlin MultiCoupon calculated amount', [
                'quote_id' => $quote->getId(),
                'item_id' => $item->getId(),
                'sku' => $item->getSku(),
                'code' => $code,
                'rule_id' => (int)$rule->getId(),
                'base_amount' => $baseAmount,
                'quote_amount' => $baseAmount * $storeBaseToQuoteRate
            ]);

            if ($baseAmount <= $best['base_amount']) {
                continue;
            }

            $best = [
                'code' => $code,
                'rule_id' => (int)$rule->getId(),
                'base_amount' => $baseAmount,
                'amount' => $baseAmount * $storeBaseToQuoteRate,
            ];
        }

        return $best;
    }
}
