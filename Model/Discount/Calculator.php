<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Model\Discount;

use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\SalesRule\Model\Rule;

class Calculator
{
    /**
     * Calculate the discount amount for an item under the provided sales rule.
     *
     * This store's sales rules are behaving like Magento's native discount box:
     * percentage discounts are applied against the row total including tax.
     *
     * @param AbstractItem $item
     * @param Rule $rule
     * @return float
     */
    public function calculate(AbstractItem $item, Rule $rule): float
    {
        $qty = max(1.0, (float)$item->getQty());

        $baseRowTotalExclTax = (float)$item->getBaseRowTotal();
        $baseRowTotalInclTax = (float)$item->getBaseRowTotalInclTax();

        $baseRowTotal = $baseRowTotalInclTax > 0.0001
            ? $baseRowTotalInclTax
            : $baseRowTotalExclTax;

        $discountAmount = (float)$rule->getDiscountAmount();
        $action = (string)$rule->getSimpleAction();

        return match ($action) {
            'by_percent' => round($baseRowTotal * ($discountAmount / 100), 4),
            'by_fixed'   => min(round($discountAmount * $qty, 4), $baseRowTotal),
            'cart_fixed' => min(round($discountAmount * $qty, 4), $baseRowTotal),
            default      => 0.0,
        };
    }
}
