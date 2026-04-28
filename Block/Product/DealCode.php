<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Block\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Merlin\MultiCoupon\Model\Config;

class DealCode extends Template
{
    /**
     * @param Template\Context $context
     * @param Registry $registry
     * @param CheckoutSession $checkoutSession
     * @param ProductRepositoryInterface $productRepository
     * @param EavConfig $eavConfig
     * @param Config $config
     * @param array<string, mixed> $data
     */
    public function __construct(
        Template\Context $context,
        private readonly Registry $registry,
        private readonly CheckoutSession $checkoutSession,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly EavConfig $eavConfig,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Determine whether the product-page deal-code block should be shown.
     *
     * @return bool
     */
    public function canShow(): bool
    {
        return $this->config->isEnabled((int)$this->_storeManager->getStore()->getId());
    }

    /**
     * Return current product from registry.
     *
     * @return ProductInterface|null
     */
    public function getProduct(): ?ProductInterface
    {
        if (!$this->canShow()) {
            return null;
        }

        $product = $this->registry->registry('current_product');
        return $product instanceof ProductInterface ? $product : null;
    }

    /**
     * Return raw coupon code for the current product.
     *
     * @return string|null
     */
    public function getDealCode(): ?string
    {
        if (!$this->canShow()) {
            return null;
        }

        $product = $this->getProduct();
        if (!$product || !(int)$product->getId()) {
            return null;
        }

        $storeId = (int)$product->getStoreId();

        try {
            $loadedProduct = $this->productRepository->getById((int)$product->getId(), false, $storeId);
        } catch (\Throwable) {
            return null;
        }

        $attribute = $this->eavConfig->getAttribute('catalog_product', 'google_promo_code');
        if (!$attribute || !(int)$attribute->getId() || !$attribute->usesSource()) {
            return null;
        }

        $rawValue = $loadedProduct->getData('google_promo_code');
        if ($rawValue === null || $rawValue === '' || $rawValue === false) {
            return null;
        }

        $label = $attribute->getSource()->getOptionText($rawValue);

        if (is_array($label)) {
            $label = reset($label);
        }

        if (!is_string($label) || trim($label) === '') {
            return null;
        }

        $label = strtoupper(trim($label));

        return in_array($label, $this->config->getAllowedCodes(), true)
            ? $label
            : null;
    }

    /**
     * Return display label for the current product deal code.
     *
     * @return string|null
     */
    public function getDealCodeLabel(): ?string
    {
        $code = $this->getDealCode();
        if ($code === null) {
            return null;
        }

        return $this->config->getDealCodeLabel($code);
    }

    /**
     * Return whether an OFFER-* coupon is currently applied.
     *
     * @return bool
     */
    public function isOfferCouponApplied(): bool
    {
        if (!$this->canShow()) {
            return false;
        }

        return str_starts_with(
            (string)$this->checkoutSession->getQuote()->getCouponCode(),
            'OFFER-'
        );
    }

    /**
     * Return apply URL.
     *
     * @return string
     */
    public function getApplyUrl(): string
    {
        return $this->getUrl('multicoupon/cart/addcoupon');
    }

    /**
     * Suppress block output when the module is disabled.
     *
     * @return string
     */
    protected function _toHtml(): string
    {
        if (!$this->canShow()) {
            return '';
        }

        return parent::_toHtml();
    }
}
