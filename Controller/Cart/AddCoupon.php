<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Controller\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Merlin\MultiCoupon\Model\QuoteCouponStorage;
use Merlin\MultiCoupon\Model\Config;

class AddCoupon extends Action
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
     * Add the posted coupon code to the active cart and redirect back to the cart.
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $code = (string)$this->getRequest()->getParam('code');
        $normalizedCode = $this->config->normalizeCode($code);

        if ($normalizedCode === '' || !$this->config->isAllowedCode($normalizedCode)) {
            $this->messageManager->addErrorMessage(__('Coupon code is not allowed.'));
            return $resultRedirect->setPath('checkout/cart');
        }

        $quote = $this->checkoutSession->getQuote();
        $this->quoteCouponStorage->addCode($quote, $normalizedCode);
        $quote->collectTotals()->save();

        $this->messageManager->addSuccessMessage(__('Coupon %1 was added.', $normalizedCode));
        return $resultRedirect->setPath('checkout/cart');
    }
}
