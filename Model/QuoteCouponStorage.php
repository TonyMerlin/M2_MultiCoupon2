<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Model;

use Magento\Quote\Api\Data\CartInterface;

class QuoteCouponStorage
{
    public const FIELD = 'merlin_multi_coupon_codes';

    /**
     * @param Config $config
     */
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * Return the normalized multi-coupon codes stored on the quote.
     *
     * @param CartInterface $quote
     * @return string[]
     */
    public function getCodes(CartInterface $quote): array
    {
        $raw = (string)$quote->getData(self::FIELD);
        if ($raw === '') {
            return [];
        }

        $codes = array_filter(array_map([$this->config, 'normalizeCode'], explode(',', $raw)));
        return array_values(array_unique($codes));
    }

    /**
     * Persist the normalized list of multi-coupon codes to the quote.
     *
     * @param CartInterface $quote
     * @param string[] $codes
     * @return string[]
     */
    public function saveCodes(CartInterface $quote, array $codes): array
    {
        $codes = array_filter(array_map([$this->config, 'normalizeCode'], $codes));
        $codes = array_values(array_unique($codes));
        $quote->setData(self::FIELD, implode(',', $codes));

        return $codes;
    }

    /**
     * Add a coupon code to the quote and return the resulting set.
     *
     * @param CartInterface $quote
     * @param string $code
     * @return string[]
     */
    public function addCode(CartInterface $quote, string $code): array
    {
        $codes = $this->getCodes($quote);
        $codes[] = $code;
        return $this->saveCodes($quote, $codes);
    }

    /**
     * Remove a coupon code from the quote and return the resulting set.
     *
     * @param CartInterface $quote
     * @param string $code
     * @return string[]
     */
    public function removeCode(CartInterface $quote, string $code): array
    {
        $code = $this->config->normalizeCode($code);
        $codes = array_filter(
            $this->getCodes($quote),
            static fn(string $existing): bool => $existing !== $code
        );

        return $this->saveCodes($quote, $codes);
    }

    /**
     * Clear all multi-coupon codes from the quote.
     *
     * @param CartInterface $quote
     * @return void
     */
    public function clearCodes(CartInterface $quote): void
    {
        $quote->setData(self::FIELD, '');
    }
}
