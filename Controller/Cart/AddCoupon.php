<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Controller\Cart;

use Locale;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Merlin\MultiCoupon\Model\Config;
use Merlin\MultiCoupon\Model\Discount\ItemRuleMatcher;
use Merlin\MultiCoupon\Model\PromoCodeResolver;
use Merlin\MultiCoupon\Model\QuoteCouponStorage;
use Merlin\MultiCoupon\Model\RuleRepository;
use Zend_Filter_LocalizedToNormalized;

class AddCoupon extends Action
{
    public function __construct(
        Context $context,
        private readonly CheckoutSession $checkoutSession,
        private readonly Cart $cart,
        private readonly QuoteCouponStorage $quoteCouponStorage,
        private readonly Config $config,
        private readonly RuleRepository $ruleRepository,
        private readonly ItemRuleMatcher $itemRuleMatcher,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly PromoCodeResolver $promoCodeResolver,
        private readonly StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        $code = (string)($this->getRequest()->getParam('coupon_code') ?: $this->getRequest()->getParam('code'));
        $source = (string)$this->getRequest()->getParam('source');
        $productId = (int)$this->getRequest()->getParam('product_id');

        $quote = $this->checkoutSession->getQuote();
        $storeId = ($quote && $quote->getId())
            ? (int)$quote->getStoreId()
            : (int)$this->storeManager->getStore()->getId();

        if (!$this->config->isEnabled($storeId)) {
            $this->messageManager->addErrorMessage(__('Multi coupon is currently disabled.'));
            return $resultRedirect->setUrl($this->_redirect->getRefererUrl());
        }

        $normalizedCode = $this->config->normalizeCode($code);

        if ($normalizedCode === '' || !$this->config->isAllowedCode($normalizedCode)) {
            $this->messageManager->addErrorMessage(__('Coupon code is not allowed.'));
            return $resultRedirect->setUrl($this->_redirect->getRefererUrl());
        }

        $sessionQuote = $this->checkoutSession->getQuote();
        $rule = $this->ruleRepository->getRuleByCode($sessionQuote, $normalizedCode);

        if (!$rule) {
            $this->messageManager->addErrorMessage(__('Coupon code is not valid.'));
            return $resultRedirect->setUrl($this->_redirect->getRefererUrl());
        }

        $codeAlreadyApplied = in_array($normalizedCode, $this->quoteCouponStorage->getCodes($sessionQuote), true);

        if ($source === 'product_page') {
            if ($productId <= 0) {
                $this->messageManager->addErrorMessage(__('Unable to validate coupon code for this product.'));
                return $resultRedirect->setUrl($this->_redirect->getRefererUrl());
            }

            try {
                $product = $this->productRepository->getById($productId, false, (int)$sessionQuote->getStoreId());

                if ($this->config->isDealCode($normalizedCode)) {
                    $resolvedCode = $this->promoCodeResolver->resolveCodeFromProduct($product);

                    if ($resolvedCode === null || $this->config->normalizeCode($resolvedCode) !== $normalizedCode) {
                        $this->messageManager->addErrorMessage(
                            __('Coupon code "%1" does not apply to this product.', $normalizedCode)
                        );
                        return $resultRedirect->setUrl($this->_redirect->getRefererUrl());
                    }
                }

                $requestParams = $this->getRequest()->getParams();

                $addToCartData = [
                    'product' => (int)$product->getId(),
                ];

                if (isset($requestParams['qty']) && $requestParams['qty'] !== '') {
                    try {
                        $filter = new LocalizedToNormalized(['locale' => Locale::getDefault()]);
                        $addToCartData['qty'] = $filter->filter((string)$requestParams['qty']);
                    } catch (\Throwable) {
                        $addToCartData['qty'] = $requestParams['qty'];
                    }
                }

                $passthroughKeys = [
                    'super_attribute',
                    'super_group',
                    'options',
                    'bundle_option',
                    'bundle_option_qty',
                    'links',
                    'related_product',
                    'selected_configurable_option',
                ];

                foreach ($passthroughKeys as $key) {
                    if (array_key_exists($key, $requestParams)) {
                        $addToCartData[$key] = $requestParams[$key];
                    }
                }

                $requestInfo = new DataObject($addToCartData);

                $cartQuote = $this->cart->getQuote();
                $productAlreadyInCart = false;

                foreach ($cartQuote->getAllVisibleItems() as $item) {
                    if ((int)$item->getProductId() === (int)$product->getId()) {
                        $productAlreadyInCart = true;
                        break;
                    }
                }

                if (!$productAlreadyInCart) {
                    $this->cart->addProduct($product, $requestInfo);
                    $cartQuote = $this->cart->getQuote();
                }

                if ($this->config->isOfferCode($normalizedCode)) {
                    $offerMatchesProduct = false;

                    foreach ($cartQuote->getAllVisibleItems() as $item) {
                        if ((int)$item->getProductId() !== (int)$product->getId()) {
                            continue;
                        }

                        if ($this->itemRuleMatcher->isMatch($item, $rule)) {
                            $offerMatchesProduct = true;
                            break;
                        }
                    }

                    if (!$offerMatchesProduct) {
                        $this->messageManager->addErrorMessage(
                            __('Coupon code "%1" does not apply to this product.', $normalizedCode)
                        );
                        return $resultRedirect->setUrl($this->_redirect->getRefererUrl());
                    }
                }

                if (!$codeAlreadyApplied) {
                    $this->quoteCouponStorage->addCode($cartQuote, $normalizedCode);
                }

                $cartQuote->setTotalsCollectedFlag(false);
                $cartQuote->collectTotals();

                $this->cart->save();
                $this->checkoutSession->setCartWasUpdated(true);

                $this->_eventManager->dispatch(
                    'checkout_cart_add_product_complete',
                    [
                        'product' => $product,
                        'request' => $this->getRequest(),
                        'response' => $this->getResponse(),
                    ]
                );

                if ($productAlreadyInCart && $codeAlreadyApplied) {
                    $this->messageManager->addSuccessMessage(
                        __('Coupon %1 is already applied.', $normalizedCode)
                    );
                } elseif ($productAlreadyInCart) {
                    $this->messageManager->addSuccessMessage(
                        __('Coupon %1 was applied to the product already in your basket.', $normalizedCode)
                    );
                } elseif ($codeAlreadyApplied) {
                    $this->messageManager->addSuccessMessage(
                        __('Product added to basket. Coupon %1 is already applied.', $normalizedCode)
                    );
                } else {
                    $this->messageManager->addSuccessMessage(
                        __('Product added to basket and coupon %1 was applied.', $normalizedCode)
                    );
                }

                return $resultRedirect->setPath('checkout/cart');
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                return $resultRedirect->setUrl($this->_redirect->getRefererUrl());
            } catch (\Throwable) {
                $this->messageManager->addErrorMessage(__('Unable to add this product to the basket.'));
                return $resultRedirect->setUrl($this->_redirect->getRefererUrl());
            }
        }

        if ($codeAlreadyApplied) {
            $this->messageManager->addNoticeMessage(__('Coupon %1 is already applied.', $normalizedCode));
            return $resultRedirect->setUrl($this->_redirect->getRefererUrl());
        }

        $matchingProductIds = [];
        foreach ($sessionQuote->getAllVisibleItems() as $item) {
            if ($this->itemRuleMatcher->isMatch($item, $rule)) {
                $matchingProductIds[] = (int)$item->getProductId();
            }
        }

        if (empty($matchingProductIds)) {
            $this->messageManager->addErrorMessage(
                __('Coupon code "%1" does not apply to any products currently in your basket.', $normalizedCode)
            );
            return $resultRedirect->setUrl($this->_redirect->getRefererUrl());
        }

        $this->quoteCouponStorage->addCode($sessionQuote, $normalizedCode);

        $sessionQuote->setTotalsCollectedFlag(false);
        $sessionQuote->collectTotals();
        $sessionQuote->save();

        $this->messageManager->addSuccessMessage(__('Coupon %1 was added.', $normalizedCode));
        return $resultRedirect->setUrl($this->_redirect->getRefererUrl());
    }
}
