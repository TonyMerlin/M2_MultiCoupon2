<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Api;

use Merlin\MultiCoupon\Api\Data\MultiCouponResponseInterface;

interface MultiCouponManagementInterface
{
    /**
     * Return the currently applied multi-coupon codes for the active customer quote.
     *
     * @return string[]
     */
    public function getMyCodes(): array;

    /**
     * Add a coupon code to the active customer quote.
     *
     * @param string $code
     * @return MultiCouponResponseInterface
     */
    public function addForCustomer(string $code): MultiCouponResponseInterface;

    /**
     * Remove a coupon code from the active customer quote.
     *
     * @param string $code
     * @return MultiCouponResponseInterface
     */
    public function removeForCustomer(string $code): MultiCouponResponseInterface;

    /**
     * Remove all multi-coupon codes from the active customer quote.
     *
     * @return MultiCouponResponseInterface
     */
    public function clearForCustomer(): MultiCouponResponseInterface;
}
