<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Controller\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Merlin\MultiCoupon\Model\QuoteCouponStorage;
use Merlin\MultiCoupon\Model\Config;

class RemoveCoupon extends Action
{
    /**
     * @param Context $context
     * @param CheckoutSession $checkoutSession
     * @param QuoteCouponStorage $quoteCouponStorage
     * @param Config $config
     */
    public function __construct(
        Context $context,
        private readonly CheckoutSession $checkoutSession,
        private readonly QuoteCouponStorage $quoteCouponStorage,
        private readonly Config $config
    ) {
        parent::__construct($context);
    }

    /**
     * Remove the posted coupon code from the active cart and redirect back to the cart.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $code = (string)($this->getRequest()->getParam('coupon_code') ?: $this->getRequest()->getParam('code'));
        $normalizedCode = $this->config->normalizeCode($code);

        $quote = $this->checkoutSession->getQuote();
        $this->quoteCouponStorage->removeCode($quote, $normalizedCode);

        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
        $quote->save();

        $this->messageManager->addSuccessMessage(__('Coupon %1 was removed.', $normalizedCode));
        return $resultRedirect->setPath('checkout/cart');
    }
}
