<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Model;

class Config
{
    private const DEAL_CODES = ['DEAL5', 'DEAL10', 'DEAL15', 'DEAL20', 'DEAL25'];
    private const OFFER_PREFIX = 'OFFER-';

    /**
     * Return the configured deal codes.
     *
     * @return string[]
     */
    public function getAllowedCodes(): array
    {
        return self::DEAL_CODES;
    }

    /**
     * Determine whether the supplied code is one of the fixed DEAL codes.
     *
     * @param string $code
     * @return bool
     */
    public function isDealCode(string $code): bool
    {
        return in_array($this->normalizeCode($code), self::DEAL_CODES, true);
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
