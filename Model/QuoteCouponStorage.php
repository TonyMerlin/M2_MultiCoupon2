<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Model;

use Magento\Quote\Api\Data\CartInterface;

class QuoteCouponStorage
{
    public const FIELD = 'merlin_multi_coupon_codes';

    public function __construct(private readonly Config $config)
    {
    }

    public function getCodes(CartInterface $quote): array
    {
        $raw = (string)$quote->getData(self::FIELD);
        if ($raw === '') {
            return [];
        }

        $codes = array_map([$this->config, 'normalizeCode'], explode(',', $raw));
        $codes = array_values(array_unique(array_filter($codes)));

        return $codes;
    }

    public function saveCodes(CartInterface $quote, array $codes): array
    {
        $codes = array_map([$this->config, 'normalizeCode'], $codes);
        $codes = array_values(array_unique(array_filter($codes)));
        $quote->setData(self::FIELD, $codes ? implode(',', $codes) : null);

        return $codes;
    }

    public function addCode(CartInterface $quote, string $code): array
    {
        $codes = $this->getCodes($quote);
        $codes[] = $code;
        return $this->saveCodes($quote, $codes);
    }

    public function removeCode(CartInterface $quote, string $code): array
    {
        $remove = $this->config->normalizeCode($code);
        $codes = array_filter(
            $this->getCodes($quote),
            static fn(string $existing): bool => $existing !== $remove
        );

        return $this->saveCodes($quote, $codes);
    }

    public function clearCodes(CartInterface $quote): void
    {
        $quote->setData(self::FIELD, null);
    }
}
