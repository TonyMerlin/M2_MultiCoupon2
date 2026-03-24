<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Model\Discount;

use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\SalesRule\Model\Rule;

class ItemRuleMatcher
{
    /**
     * Determine whether the quote item matches the provided sales rule actions.
     *
     * @param AbstractItem $item
     * @param Rule $rule
     * @return bool
     */
    public function isMatch(AbstractItem $item, Rule $rule): bool
    {
        if (!$rule->getActions()) {
            return true;
        }

        return (bool)$rule->getActions()->validate($item);
    }
}
