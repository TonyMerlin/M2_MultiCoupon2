<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Model;

use Magento\Quote\Api\Data\CartInterface;

class QuoteCouponStorage
{
    private const FIELD = 'merlin_multi_coupon_codes';

    public function getCodes(CartInterface $quote): array
    {
        $raw = (string)$quote->getData(self::FIELD);
        if ($raw === '') {
            return [];
        }

        $codes = array_filter(array_map('trim', explode(',', strtoupper($raw))));
        $codes = array_values(array_unique($codes));

        return $codes;
    }

    public function saveCodes(CartInterface $quote, array $codes): void
    {
        $codes = array_map(
            static fn(string $code): string => strtoupper(trim($code)),
            $codes
        );
        $codes = array_values(array_unique(array_filter($codes)));
        $quote->setData(self::FIELD, implode(',', $codes));
    }

    public function addCode(CartInterface $quote, string $code): array
    {
        $codes = $this->getCodes($quote);
        $codes[] = strtoupper(trim($code));
        $this->saveCodes($quote, $codes);

        return $this->getCodes($quote);
    }

    public function removeCode(CartInterface $quote, string $code): array
    {
        $remove = strtoupper(trim($code));
        $codes = array_filter(
            $this->getCodes($quote),
            static fn(string $existing): bool => $existing !== $remove
        );

        $this->saveCodes($quote, $codes);

        return $this->getCodes($quote);
    }

    public function clearCodes(CartInterface $quote): void
    {
        $quote->setData(self::FIELD, null);
    }
}
