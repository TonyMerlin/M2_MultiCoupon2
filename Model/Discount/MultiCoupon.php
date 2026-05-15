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

class MultiCoupon extends AbstractTotal
{
    public function __construct(
        private readonly QuoteCouponStorage $quoteCouponStorage,
        private readonly RuleRepository $ruleRepository,
        private readonly ItemRuleMatcher $itemRuleMatcher,
        private readonly Calculator $calculator,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly LoggerInterface $logger
    ) {
        /*
         * IMPORTANT:
         * Use Magento's native discount code so discount_amount survives into
         * order/invoice/payment flows.
         */
        $this->setCode('discount');
    }

    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ): self {
        parent::collect($quote, $shippingAssignment, $total);

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

        /*
         * Set native discount fields directly, not cumulatively, so invoice/order
         * generation reads the real discount.
         */
        $total->setDiscountAmount(-$discountTotal);
        $total->setBaseDiscountAmount(-$baseDiscountTotal);

        $subtotalWithDiscount = (float)$total->getSubtotal() - $discountTotal;
        $baseSubtotalWithDiscount = (float)$total->getBaseSubtotal() - $baseDiscountTotal;

        $total->setSubtotalWithDiscount($subtotalWithDiscount);
        $total->setBaseSubtotalWithDiscount($baseSubtotalWithDiscount);

        $description = implode(', ', array_values($appliedCodes));

        if ($address instanceof Address) {
            $address->setDiscountDescription($description);
            $address->setDiscountAmount(-$discountTotal);
            $address->setBaseDiscountAmount(-$baseDiscountTotal);
            $address->setSubtotalWithDiscount($subtotalWithDiscount);
            $address->setBaseSubtotalWithDiscount($baseSubtotalWithDiscount);
        }

        /*
         * Also mirror discount values onto the real quote address model that
         * payment/invoice exporters tend to read from.
         */
        $quoteAddress = $quote->isVirtual()
            ? $quote->getBillingAddress()
            : $quote->getShippingAddress();

        if ($quoteAddress instanceof Address) {
            $quoteAddress->setDiscountDescription($description);
            $quoteAddress->setDiscountAmount(-$discountTotal);
            $quoteAddress->setBaseDiscountAmount(-$baseDiscountTotal);
            $quoteAddress->setSubtotalWithDiscount($subtotalWithDiscount);
            $quoteAddress->setBaseSubtotalWithDiscount($baseSubtotalWithDiscount);
        }

        $quote->setData(QuoteCouponStorage::FIELD, implode(',', $codes));
        $quote->setAppliedRuleIds(implode(',', array_map('strval', array_values($appliedRuleIds))));
        $quote->setDiscountAmount(-$discountTotal);
        $quote->setBaseDiscountAmount(-$baseDiscountTotal);
        $quote->setSubtotalWithDiscount($subtotalWithDiscount);
        $quote->setBaseSubtotalWithDiscount($baseSubtotalWithDiscount);

        $this->logger->debug('MC collect final totals', [
            'quote_id' => $quote->getId(),
            'code' => $this->getCode(),
            'codes' => $codes,
            'base_discount_total' => $baseDiscountTotal,
            'discount_total' => $discountTotal,
            'total_discount_amount' => $total->getDiscountAmount(),
            'total_base_discount_amount' => $total->getBaseDiscountAmount(),
            'total_subtotal' => $total->getSubtotal(),
            'total_base_subtotal' => $total->getBaseSubtotal(),
            'total_tax' => $total->getTaxAmount(),
            'total_base_tax' => $total->getBaseTaxAmount(),
            'total_shipping' => $total->getShippingAmount(),
            'total_base_shipping' => $total->getBaseShippingAmount(),
            'total_grand' => $total->getGrandTotal(),
            'total_base_grand' => $total->getBaseGrandTotal(),
            'quote_discount_amount' => $quote->getDiscountAmount(),
            'quote_base_discount_amount' => $quote->getBaseDiscountAmount(),
            'quote_subtotal_with_discount' => $quote->getSubtotalWithDiscount(),
            'quote_base_subtotal_with_discount' => $quote->getBaseSubtotalWithDiscount(),
            'quote_address_discount_amount' => $quoteAddress instanceof Address ? $quoteAddress->getDiscountAmount() : null,
            'quote_address_base_discount_amount' => $quoteAddress instanceof Address ? $quoteAddress->getBaseDiscountAmount() : null,
            'quote_address_subtotal_with_discount' => $quoteAddress instanceof Address ? $quoteAddress->getSubtotalWithDiscount() : null,
            'quote_address_base_subtotal_with_discount' => $quoteAddress instanceof Address ? $quoteAddress->getBaseSubtotalWithDiscount() : null,
        ]);

        @file_put_contents(
            BP . '/var/log/merlin_multicoupon_collect.log',
            print_r([
                'quote_id' => $quote->getId(),
                'code' => $this->getCode(),
                'codes' => $codes,
                'base_discount_total' => $baseDiscountTotal,
                'discount_total' => $discountTotal,
                'total_discount_amount' => $total->getDiscountAmount(),
                'total_base_discount_amount' => $total->getBaseDiscountAmount(),
                'total_subtotal' => $total->getSubtotal(),
                'total_base_subtotal' => $total->getBaseSubtotal(),
                'total_tax' => $total->getTaxAmount(),
                'total_base_tax' => $total->getBaseTaxAmount(),
                'total_shipping' => $total->getShippingAmount(),
                'total_base_shipping' => $total->getBaseShippingAmount(),
                'total_grand' => $total->getGrandTotal(),
                'total_base_grand' => $total->getBaseGrandTotal(),
                'quote_discount_amount' => $quote->getDiscountAmount(),
                'quote_base_discount_amount' => $quote->getBaseDiscountAmount(),
                'quote_subtotal_with_discount' => $quote->getSubtotalWithDiscount(),
                'quote_base_subtotal_with_discount' => $quote->getBaseSubtotalWithDiscount(),
                'quote_address_discount_amount' => $quoteAddress instanceof Address ? $quoteAddress->getDiscountAmount() : null,
                'quote_address_base_discount_amount' => $quoteAddress instanceof Address ? $quoteAddress->getBaseDiscountAmount() : null,
                'quote_address_subtotal_with_discount' => $quoteAddress instanceof Address ? $quoteAddress->getSubtotalWithDiscount() : null,
                'quote_address_base_subtotal_with_discount' => $quoteAddress instanceof Address ? $quoteAddress->getBaseSubtotalWithDiscount() : null,
            ], true) . "\n----------------------\n",
            FILE_APPEND
        );

        return $this;
    }

    public function fetch(Quote $quote, Total $total): array
    {
        $codes = $this->quoteCouponStorage->getCodes($quote);
        $discountAmount = (float)$total->getDiscountAmount();

        if (!$codes || abs($discountAmount) < 0.0001) {
            return [];
        }

        return [
            'code' => $this->getCode(),
            'title' => __('Discount (%1)', implode(', ', $codes)),
            'value' => $discountAmount,
        ];
    }

    private function resetAddressTotals(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total,
        array $items
    ): void {
        $total->setTotalAmount($this->getCode(), 0.0);
        $total->setBaseTotalAmount($this->getCode(), 0.0);
        $total->setDiscountAmount(0.0);
        $total->setBaseDiscountAmount(0.0);

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
            $address->setSubtotalWithDiscount(null);
            $address->setBaseSubtotalWithDiscount(null);
        }
    }

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
