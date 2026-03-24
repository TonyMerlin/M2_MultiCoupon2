<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Model\Discount;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Merlin\MultiCoupon\Model\QuoteCouponStorage;
use Merlin\MultiCoupon\Model\RuleRepository;

class MultiCoupon extends AbstractTotal
{
    public function __construct(
        private readonly QuoteCouponStorage $quoteCouponStorage,
        private readonly RuleRepository $ruleRepository,
        private readonly ItemRuleMatcher $itemRuleMatcher,
        private readonly Calculator $calculator,
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
        $this->setCode('merlin_multi_coupon_discount');
    }

    public function collect(Quote $quote, Address $address, Total $total): self
    {
        parent::collect($quote, $address, $total);

        if (!$quote->getItemsCount()) {
            return $this;
        }

        $this->resetAddressTotals($address, $total);

        $codes = $this->quoteCouponStorage->getCodes($quote);
        if (!$codes) {
            return $this;
        }

        $appliedCodes = [];
        $appliedRuleIds = [];
        $baseDiscountTotal = 0.0;
        $discountTotal = 0.0;

        foreach ($address->getAllItems() as $item) {
            if ($item->getParentItem()) {
                continue;
            }

            $best = $this->getBestDiscountForItem($quote, $item, $codes);
            if ($best['base_amount'] <= 0.0001) {
                continue;
            }

            $baseAmount = $this->priceCurrency->round((float)$best['base_amount']);
            $amount = $this->priceCurrency->round((float)$best['amount']);

            $item->setBaseDiscountAmount((float)$item->getBaseDiscountAmount() + $baseAmount);
            $item->setDiscountAmount((float)$item->getDiscountAmount() + $amount);
            $item->setOriginalDiscountAmount((float)$item->getOriginalDiscountAmount() + $amount);
            $item->setBaseOriginalDiscountAmount((float)$item->getBaseOriginalDiscountAmount() + $baseAmount);

            $baseDiscountTotal += $baseAmount;
            $discountTotal += $amount;
            $appliedCodes[$best['code']] = $best['code'];
            $appliedRuleIds[$best['rule_id']] = $best['rule_id'];
        }

        if ($baseDiscountTotal <= 0.0001) {
            return $this;
        }

        $total->addTotalAmount($this->getCode(), -$discountTotal);
        $total->addBaseTotalAmount($this->getCode(), -$baseDiscountTotal);
        $total->setDiscountAmount((float)$total->getDiscountAmount() - $discountTotal);
        $total->setBaseDiscountAmount((float)$total->getBaseDiscountAmount() - $baseDiscountTotal);

        $description = implode(', ', array_values($appliedCodes));
        $existingDescription = trim((string)$address->getDiscountDescription());
        if ($existingDescription !== '') {
            $description = $existingDescription . ', ' . $description;
        }
        $address->setDiscountDescription($description);
        $quote->setData(QuoteCouponStorage::FIELD, implode(',', $codes));

        $existingRuleIds = trim((string)$quote->getAppliedRuleIds());
        $mergedRuleIds = array_filter(array_unique(array_merge(
            $existingRuleIds !== '' ? explode(',', $existingRuleIds) : [],
            array_map('strval', array_values($appliedRuleIds))
        )));
        $quote->setAppliedRuleIds(implode(',', $mergedRuleIds));

        return $this;
    }

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

    private function resetAddressTotals(Address $address, Total $total): void
    {
        $total->setTotalAmount($this->getCode(), 0.0);
        $total->setBaseTotalAmount($this->getCode(), 0.0);

        foreach ($address->getAllItems() as $item) {
            if ($item->getParentItem()) {
                continue;
            }

            $item->setDiscountAmount((float)$item->getDiscountAmount());
            $item->setBaseDiscountAmount((float)$item->getBaseDiscountAmount());
        }
    }

    /**
     * @return array{code:string,rule_id:int,base_amount:float,amount:float}
     */
    private function getBestDiscountForItem(Quote $quote, AbstractItem $item, array $codes): array
    {
        $best = ['code' => '', 'rule_id' => 0, 'base_amount' => 0.0, 'amount' => 0.0];
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
