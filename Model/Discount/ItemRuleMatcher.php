<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Model\Discount;

use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\SalesRule\Model\Rule;

class ItemRuleMatcher
{
    public function isMatch(AbstractItem $item, Rule $rule): bool
    {
        $product = $item->getProduct();
        if (!$product || !$product->getId()) {
            return false;
        }

        $actions = $rule->getActions();
        if ($actions && method_exists($actions, 'validate')) {
            return (bool)$actions->validate($item);
        }

        return true;
    }
}
