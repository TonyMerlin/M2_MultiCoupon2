<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Model;

use Magento\Framework\DataObject;
use Merlin\MultiCoupon\Api\Data\MultiCouponResponseInterface;

class MultiCouponResponse extends DataObject implements MultiCouponResponseInterface
{
    public function getSuccess(): bool
    {
        return (bool)$this->getData(self::SUCCESS);
    }

    public function setSuccess(bool $success): MultiCouponResponseInterface
    {
        return $this->setData(self::SUCCESS, $success);
    }

    public function getMessage(): string
    {
        return (string)$this->getData(self::MESSAGE);
    }

    public function setMessage(string $message): MultiCouponResponseInterface
    {
        return $this->setData(self::MESSAGE, $message);
    }

    public function getCodes(): array
    {
        return (array)$this->getData(self::CODES);
    }

    public function setCodes(array $codes): MultiCouponResponseInterface
    {
        return $this->setData(self::CODES, $codes);
    }
}
