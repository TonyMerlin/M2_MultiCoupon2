<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Controller\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Merlin\MultiCoupon\Model\QuoteCouponStorage;

class ClearCoupons extends Action
{
    /**
     * @param Context $context
     * @param CheckoutSession $checkoutSession
     * @param QuoteCouponStorage $quoteCouponStorage
     */
    public function __construct(
        Context $context,
        private readonly CheckoutSession $checkoutSession,
        private readonly QuoteCouponStorage $quoteCouponStorage
    ) {
        parent::__construct($context);
    }

    /**
     * Clear all multi-coupon codes from the active cart and redirect back to the cart.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $quote = $this->checkoutSession->getQuote();
        $this->quoteCouponStorage->clearCodes($quote);
        $quote->collectTotals()->save();

        $this->messageManager->addSuccessMessage(__('All deal coupon codes were cleared.'));
        return $resultRedirect->setPath('checkout/cart');
    }
}
