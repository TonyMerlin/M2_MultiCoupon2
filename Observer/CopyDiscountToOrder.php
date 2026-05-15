<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;

class CopyDiscountToOrder implements ObserverInterface
{
    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getQuote();
        $order = $observer->getEvent()->getOrder();

        if (!$quote instanceof Quote || !$order instanceof Order) {
            return;
        }

        $discountAmount = (float)$quote->getDiscountAmount();
        $baseDiscountAmount = (float)$quote->getBaseDiscountAmount();

        if (abs($discountAmount) < 0.0001 && abs($baseDiscountAmount) < 0.0001) {
            return;
        }

        $discountDescription = null;

        $shippingAddress = $quote->getShippingAddress();
        $billingAddress = $quote->getBillingAddress();

        if ($shippingAddress && $shippingAddress->getDiscountDescription()) {
            $discountDescription = (string)$shippingAddress->getDiscountDescription();
        } elseif ($billingAddress && $billingAddress->getDiscountDescription()) {
            $discountDescription = (string)$billingAddress->getDiscountDescription();
        }

        $order->setDiscountAmount($discountAmount);
        $order->setBaseDiscountAmount($baseDiscountAmount);
        $order->setDiscountDescription($discountDescription);

        $subtotalWithDiscount = $quote->getSubtotalWithDiscount();
        $baseSubtotalWithDiscount = $quote->getBaseSubtotalWithDiscount();

        if ($subtotalWithDiscount !== null) {
            $order->setSubtotalWithDiscount((float)$subtotalWithDiscount);
        }
        if ($baseSubtotalWithDiscount !== null) {
            $order->setBaseSubtotalWithDiscount((float)$baseSubtotalWithDiscount);
        }
    }
}
