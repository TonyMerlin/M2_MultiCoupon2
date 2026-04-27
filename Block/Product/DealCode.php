<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Block\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;

class DealCode extends Template
{
    /**
     * @param Template\Context $context
     * @param Registry $registry
     * @param CheckoutSession $checkoutSession
     * @param ProductRepositoryInterface $productRepository
     * @param EavConfig $eavConfig
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        private readonly Registry $registry,
        private readonly CheckoutSession $checkoutSession,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly EavConfig $eavConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Return current product from registry.
     *
     * @return ProductInterface|null
     */
    public function getProduct(): ?ProductInterface
    {
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

        return in_array($label, ['DEAL5', 'DEAL10', 'DEAL15', 'DEAL20', 'DEAL25'], true)
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
        return match ($this->getDealCode()) {
            'DEAL5' => 'Extra 5% OFF',
            'DEAL10' => 'Extra 10% OFF',
            'DEAL15' => 'Extra 15% OFF',
            'DEAL20' => 'Extra 20% OFF',
            'DEAL25' => 'Extra 25% OFF',
            default => null,
        };
    }

    /**
     * Return whether an OFFER-* coupon is currently applied.
     *
     * @return bool
     */
    public function isOfferCouponApplied(): bool
    {
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
}
