<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Api\Data;

interface MultiCouponResponseInterface
{
    public const SUCCESS = 'success';
    public const MESSAGE = 'message';
    public const CODES = 'codes';

    /**
     * Return whether the operation succeeded.
     *
     * @return bool
     */
    public function getSuccess(): bool;

    /**
     * Set whether the operation succeeded.
     *
     * @param bool $success
     * @return self
     */
    public function setSuccess(bool $success): self;

    /**
     * Return the operation message.
     *
     * @return string
     */
    public function getMessage(): string;

    /**
     * Set the operation message.
     *
     * @param string $message
     * @return self
     */
    public function setMessage(string $message): self;

    /**
     * Return the applied coupon codes.
     *
     * @return string[]
     */
    public function getCodes(): array;

    /**
     * Set the applied coupon codes.
     *
     * @param string[] $codes
     * @return self
     */
    public function setCodes(array $codes): self;
}
