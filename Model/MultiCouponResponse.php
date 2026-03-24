<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Model;

use Magento\Framework\DataObject;
use Merlin\MultiCoupon\Api\Data\MultiCouponResponseInterface;

class MultiCouponResponse extends DataObject implements MultiCouponResponseInterface
{
    /**
     * Return whether the operation succeeded.
     *
     * @return bool
     */
    public function getSuccess(): bool
    {
        return (bool)$this->getData(self::SUCCESS);
    }

    /**
     * Set whether the operation succeeded.
     *
     * @param bool $success
     * @return MultiCouponResponseInterface
     */
    public function setSuccess(bool $success): MultiCouponResponseInterface
    {
        return $this->setData(self::SUCCESS, $success);
    }

    /**
     * Return the operation message.
     *
     * @return string
     */
    public function getMessage(): string
    {
        return (string)$this->getData(self::MESSAGE);
    }

    /**
     * Set the operation message.
     *
     * @param string $message
     * @return MultiCouponResponseInterface
     */
    public function setMessage(string $message): MultiCouponResponseInterface
    {
        return $this->setData(self::MESSAGE, $message);
    }

    /**
     * Return the applied coupon codes.
     *
     * @return string[]
     */
    public function getCodes(): array
    {
        return (array)$this->getData(self::CODES);
    }

    /**
     * Set the applied coupon codes.
     *
     * @param string[] $codes
     * @return MultiCouponResponseInterface
     */
    public function setCodes(array $codes): MultiCouponResponseInterface
    {
        return $this->setData(self::CODES, array_values($codes));
    }
}
