<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Merlin\MultiCoupon\Model\QuoteCouponStorage;

class ClearNativeCouponObserver implements ObserverInterface
{
    public function __construct(private readonly QuoteCouponStorage $quoteCouponStorage)
    {
    }

    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getQuote();
        if (!$quote || !$quote->getId()) {
            return;
        }

        if ($this->quoteCouponStorage->getCodes($quote)) {
            $quote->setCouponCode('');
        }
    }
}
