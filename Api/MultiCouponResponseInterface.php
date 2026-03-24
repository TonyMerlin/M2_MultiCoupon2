<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Api\Data;

interface MultiCouponResponseInterface
{
    public const SUCCESS = 'success';
    public const MESSAGE = 'message';
    public const CODES = 'codes';

    public function getSuccess(): bool;
    public function setSuccess(bool $success): self;

    public function getMessage(): string;
    public function setMessage(string $message): self;

    /**
     * @return string[]
     */
    public function getCodes(): array;

    /**
     * @param string[] $codes
     */
    public function setCodes(array $codes): self;
}
