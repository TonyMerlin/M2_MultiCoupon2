<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Api;

use Merlin\MultiCoupon\Api\Data\MultiCouponResponseInterface;

interface MultiCouponManagementInterface
{
    public function getMyCodes(): array;

    public function addForCustomer(string $code): MultiCouponResponseInterface;

    public function removeForCustomer(string $code): MultiCouponResponseInterface;

    public function clearForCustomer(): MultiCouponResponseInterface;
}
