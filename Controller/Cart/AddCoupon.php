<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Controller\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Merlin\MultiCoupon\Model\Config;
use Merlin\MultiCoupon\Model\Discount\ItemRuleMatcher;
use Merlin\MultiCoupon\Model\QuoteCouponStorage;
use Merlin\MultiCoupon\Model\RuleRepository;

class AddCoupon extends Action
{
    /**
     * @param Context $context
     * @param CheckoutSession $checkoutSession
     * @param QuoteCouponStorage $quoteCouponStorage
     * @param Config $config
     * @param RuleRepository $ruleRepository
     * @param ItemRuleMatcher $itemRuleMatcher
     */
    public function __construct(
        Context $context,
        private readonly CheckoutSession $checkoutSession,
        private readonly QuoteCouponStorage $quoteCouponStorage,
        private readonly Config $config,
        private readonly RuleRepository $ruleRepository,
        private readonly ItemRuleMatcher $itemRuleMatcher
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
        $code = (string)($this->getRequest()->getParam('coupon_code') ?: $this->getRequest()->getParam('code'));
        $normalizedCode = $this->config->normalizeCode($code);

        if ($normalizedCode === '' || !$this->config->isAllowedCode($normalizedCode)) {
            $this->messageManager->addErrorMessage(__('Coupon code is not allowed.'));
            return $resultRedirect->setPath('checkout/cart');
        }

        $quote = $this->checkoutSession->getQuote();

        $existingCodes = $this->quoteCouponStorage->getCodes($quote);
        if (in_array($normalizedCode, $existingCodes, true)) {
            $this->messageManager->addNoticeMessage(__('Coupon %1 is already applied.', $normalizedCode));
            return $resultRedirect->setPath('checkout/cart');
        }

        $rule = $this->ruleRepository->getRuleByCode($quote, $normalizedCode);
        if (!$rule) {
            $this->messageManager->addErrorMessage(__('Coupon code is not valid.'));
            return $resultRedirect->setPath('checkout/cart');
        }

        $matchesAtLeastOneItem = false;
        foreach ($quote->getAllVisibleItems() as $item) {
            if ($this->itemRuleMatcher->isMatch($item, $rule)) {
                $matchesAtLeastOneItem = true;
                break;
            }
        }

        if (!$matchesAtLeastOneItem) {
            $this->messageManager->addErrorMessage(
                __('Coupon code "%1" does not apply to any products currently in your basket.', $normalizedCode)
            );
            return $resultRedirect->setPath('checkout/cart');
        }

        $this->quoteCouponStorage->addCode($quote, $normalizedCode);

        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
        $quote->save();

        $this->messageManager->addSuccessMessage(__('Coupon %1 was added.', $normalizedCode));
        return $resultRedirect->setPath('checkout/cart');
    }
}
