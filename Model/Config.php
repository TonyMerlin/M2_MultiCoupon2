<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Merlin\MultiCoupon\Model\ResourceModel\Code\CollectionFactory as CodeCollectionFactory;

class Config
{
    private const XML_PATH_ENABLED = 'merlin_multicoupon/general/enabled';
    private const OFFER_PREFIX = 'OFFER-';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param CodeCollectionFactory $codeCollectionFactory
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CodeCollectionFactory $codeCollectionFactory
    ) {
    }

    /**
     * Determine whether the module is enabled.
     *
     * @param int|string|null $storeId
     * @return bool
     */
    public function isEnabled(int|string|null $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Return the configured active deal codes from storage.
     *
     * @return string[]
     */
    public function getAllowedCodes(): array
    {
        $collection = $this->codeCollectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);
        $collection->setOrder('sort_order', 'ASC');
        $collection->setOrder('code', 'ASC');

        $codes = [];
        foreach ($collection as $item) {
            $code = $this->normalizeCode((string)$item->getData('code'));
            if ($code !== '') {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * Return the display label for an active deal code.
     *
     * @param string $code
     * @return string|null
     */
    public function getDealCodeLabel(string $code): ?string
    {
        $normalizedCode = $this->normalizeCode($code);

        $collection = $this->codeCollectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);
        $collection->addFieldToFilter('code', $normalizedCode);
        $collection->setPageSize(1);

        $item = $collection->getFirstItem();
        if (!$item || !$item->getId()) {
            return null;
        }

        $label = trim((string)$item->getData('label'));

        return $label !== '' ? $label : null;
    }

    /**
     * Determine whether the supplied code is one of the configured DEAL codes.
     *
     * @param string $code
     * @return bool
     */
    public function isDealCode(string $code): bool
    {
        return in_array($this->normalizeCode($code), $this->getAllowedCodes(), true);
    }

    /**
     * Determine whether the supplied code is an OFFER code.
     *
     * Accepted format example:
     * OFFER-XYM2-YV3T-YUIA-9CN0
     *
     * @param string $code
     * @return bool
     */
    public function isOfferCode(string $code): bool
    {
        $code = $this->normalizeCode($code);

        if (!str_starts_with($code, self::OFFER_PREFIX)) {
            return false;
        }

        return preg_match('/^OFFER-[A-Z0-9]+(?:-[A-Z0-9]+)*$/', $code) === 1;
    }

    /**
     * Determine whether the supplied code is allowed by the module.
     *
     * @param string $code
     * @return bool
     */
    public function isAllowedCode(string $code): bool
    {
        $code = $this->normalizeCode($code);

        return $this->isDealCode($code) || $this->isOfferCode($code);
    }

    /**
     * Normalize a coupon code to the module's uppercase format.
     *
     * @param string $code
     * @return string
     */
    public function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }
}
