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
     * @param AbstractItem $item
     * @param Rule $rule
     * @return float
     */
    public function calculate(AbstractItem $item, Rule $rule): float
    {
        $qty = (float)$item->getQty();
        $rowTotal = (float)$item->getBaseRowTotal();
        $discountAmount = (float)$rule->getDiscountAmount();
        $action = (string)$rule->getSimpleAction();

        return match ($action) {
            'by_percent' => round($rowTotal * ($discountAmount / 100), 4),
            'by_fixed' => min(round($discountAmount * $qty, 4), $rowTotal),
            'cart_fixed' => min(round($discountAmount * $qty, 4), $rowTotal),
            default => 0.0,
        };
    }
}
