<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Model;

class Config
{
    private const DEFAULT_ALLOWED_CODES = ['DEAL5', 'DEAL10', 'DEAL15', 'DEAL20', 'DEAL25'];

    public function getAllowedCodes(): array
    {
        return self::DEFAULT_ALLOWED_CODES;
    }

    public function isAllowedCode(string $code): bool
    {
        return in_array($this->normalizeCode($code), $this->getAllowedCodes(), true);
    }

    public function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }
}
