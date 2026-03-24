<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Model\Discount;

use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\SalesRule\Model\Rule;

class Calculator
{
    public function calculate(AbstractItem $item, Rule $rule): float
    {
        $qty = max(0.0, (float)$item->getTotalQty() ?: (float)$item->getQty());
        $baseRowTotal = (float)$item->getBaseRowTotal();
        if ($qty <= 0 || $baseRowTotal <= 0) {
            return 0.0;
        }

        $alreadyDiscounted = abs((float)$item->getBaseDiscountAmount());
        $discountableBase = max(0.0, $baseRowTotal - $alreadyDiscounted);
        if ($discountableBase <= 0.0001) {
            return 0.0;
        }

        $action = (string)$rule->getSimpleAction();
        $amount = (float)$rule->getDiscountAmount();

        return match ($action) {
            Rule::BY_PERCENT_ACTION => min($discountableBase, $discountableBase * ($amount / 100)),
            Rule::BY_FIXED_ACTION => min($discountableBase, $amount * $qty),
            Rule::CART_FIXED_ACTION => min($discountableBase, $amount * $qty),
            default => 0.0,
        };
    }
}
