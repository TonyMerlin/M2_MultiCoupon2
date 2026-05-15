<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Plugin\Paypal;

use Magento\Paypal\Model\Cart;

class CartPlugin
{
    /**
     * Ensure PayPal cart amounts include discount for our multi-coupon flow.
     *
     * On SetExpressCheckout, Magento usually still exposes base_discount_amount.
     * On DoExpressCheckoutPayment, Magento can rebuild the quote/cart and lose
     * native discount fields while grand total remains discounted.
     *
     * In that case, derive discount from:
     * subtotal + tax + shipping - grand_total
     *
     * @param Cart $subject
     * @param array<string, float|int> $result
     * @return array<string, float|int>
     */
    public function afterGetAmounts(Cart $subject, array $result): array
    {
        try {
            if (!method_exists($subject, 'getSalesModel')) {
                return $result;
            }

            $salesModel = $subject->getSalesModel();
            if (!$salesModel) {
                return $result;
            }

            $existingDiscount = isset($result['discount']) ? (float)$result['discount'] : 0.0;
            if ($existingDiscount > 0.0001) {
                return $result;
            }

            $baseDiscountAmount = (float)$salesModel->getDataUsingMethod('base_discount_amount');
            $normalizedDiscount = abs($baseDiscountAmount);

            if ($normalizedDiscount <= 0.0001) {
                $subtotal = isset($result['subtotal']) ? (float)$result['subtotal'] : 0.0;
                $tax = isset($result['tax']) ? (float)$result['tax'] : 0.0;
                $shipping = isset($result['shipping']) ? (float)$result['shipping'] : 0.0;
                $grandTotal = (float)$salesModel->getDataUsingMethod('base_grand_total');

                $derivedDiscount = round(($subtotal + $tax + $shipping) - $grandTotal, 2);

                if ($derivedDiscount > 0.0001) {
                    $normalizedDiscount = $derivedDiscount;
                }
            }

            if ($normalizedDiscount <= 0.0001) {
                return $result;
            }

            $result['discount'] = $normalizedDiscount;

            @file_put_contents(
                BP . '/var/log/merlin_paypal_debug.log',
                "CartPlugin afterGetAmounts injected discount\n" . print_r([
                    'sales_model_class' => get_class($salesModel),
                    'base_discount_amount' => $baseDiscountAmount,
                    'base_grand_total' => $salesModel->getDataUsingMethod('base_grand_total'),
                    'subtotal' => $result['subtotal'] ?? null,
                    'tax' => $result['tax'] ?? null,
                    'shipping' => $result['shipping'] ?? null,
                    'normalized_discount' => $normalizedDiscount,
                    'result' => $result,
                ], true) . "\n----------------------\n",
                FILE_APPEND
            );
        } catch (\Throwable $e) {
            @file_put_contents(
                BP . '/var/log/merlin_paypal_debug.log',
                "CartPlugin afterGetAmounts exception: " . $e->getMessage() . "\n",
                FILE_APPEND
            );
        }

        return $result;
    }
}
