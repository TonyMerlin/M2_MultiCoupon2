<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Model;

class Config
{
    private const ALLOWED_CODES = ['DEAL5', 'DEAL10', 'DEAL15', 'DEAL20', 'DEAL25'];

    /**
     * Return the configured multi-coupon codes allowed by the module.
     *
     * @return string[]
     */
    public function getAllowedCodes(): array
    {
        return self::ALLOWED_CODES;
    }

    /**
     * Determine whether the supplied code is allowed by the module.
     *
     * @param string $code
     * @return bool
     */
    public function isAllowedCode(string $code): bool
    {
        return in_array($this->normalizeCode($code), self::ALLOWED_CODES, true);
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
