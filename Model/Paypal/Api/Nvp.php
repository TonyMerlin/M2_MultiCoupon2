<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Model\Paypal\Api;

class Nvp extends \Magento\Paypal\Model\Api\Nvp
{
    protected function _exportLineItems(array &$request, $i = 0)
    {
        @file_put_contents(
            BP . '/var/log/merlin_paypal_debug.log',
            "HIT _exportLineItems\n",
            FILE_APPEND
        );

        if (!$this->_cart) {
            @file_put_contents(
                BP . '/var/log/merlin_paypal_debug.log',
                "NO CART\n",
                FILE_APPEND
            );
            return;
        }

        @file_put_contents(
            BP . '/var/log/merlin_paypal_debug.log',
            print_r([
                'is_line_items_enabled' => $this->getIsLineItemsEnabled(),
                'quote_base_grand_total' => $this->getQuoteValue('base_grand_total'),
                'quote_base_subtotal' => $this->getQuoteValue('base_subtotal'),
                'quote_base_tax_amount' => $this->getQuoteValue('base_tax_amount'),
                'quote_base_shipping_amount' => $this->getQuoteValue('base_shipping_amount'),
                'quote_base_discount_amount' => $this->getQuoteValue('base_discount_amount'),
            ], true) . "\n",
            FILE_APPEND
        );

        if ($this->getIsLineItemsEnabled()) {
            $this->_cart->setTransferDiscountAsItem();
        }

        $amounts = $this->_cart->getAmounts();

        /*
         * When line items are disabled, NVP still expects:
         * AMT = ITEMAMT + TAXAMT + SHIPPINGAMT
         *
         * Core mapping exports subtotal as ITEMAMT and ignores discount.
         * So we must export ITEMAMT net of discount.
         */
        if (!$this->getIsLineItemsEnabled()) {
            $subtotal = isset($amounts['subtotal']) ? (float)$amounts['subtotal'] : 0.0;
            $discount = isset($amounts['discount']) ? (float)$amounts['discount'] : 0.0;
            $amounts['subtotal'] = max(0.0, $subtotal - $discount);
        }

        if ($this->_lineItemTotalExportMap) {
            foreach ($amounts as $key => $total) {
                if (isset($this->_lineItemTotalExportMap[$key])) {
                    $privateKey = $this->_lineItemTotalExportMap[$key];
                    $total = round((float)$total, 2);
                    $request[$privateKey] = $this->formatPrice($total);
                }
            }
        }

        @file_put_contents(
            BP . '/var/log/merlin_paypal_debug.log',
            "CART AMOUNTS:\n" . print_r($this->_cart->getAmounts(), true) . "\n",
            FILE_APPEND
        );

        @file_put_contents(
            BP . '/var/log/merlin_paypal_debug.log',
            "EXPORTED AMOUNTS:\n" . print_r($amounts, true) . "\n",
            FILE_APPEND
        );

        $items = $this->_cart->getAllItems();
        if (!empty($items) && $this->getIsLineItemsEnabled()) {
            $result = null;
            foreach ($items as $item) {
                @file_put_contents(
                    BP . '/var/log/merlin_paypal_debug.log',
                    "ITEM:\n" . print_r([
                        'name' => $item->getName(),
                        'qty' => $item->getQty(),
                        'amount' => $item->getAmount(),
                    ], true) . "\n",
                    FILE_APPEND
                );

                foreach ($this->_lineItemExportItemsFormat as $publicKey => $privateFormat) {
                    $result = true;
                    $value = $item->getDataUsingMethod($publicKey);
                    $request[sprintf($privateFormat, $i)] = $this->formatValue($value, $publicKey);
                }
                $i++;
            }
        }

        $interesting = [];
        foreach ($request as $k => $v) {
            if (
                str_starts_with((string)$k, 'PAYMENTREQUEST_0_')
                || str_starts_with((string)$k, 'L_PAYMENTREQUEST_0_')
            ) {
                $interesting[$k] = $v;
            }
        }

        @file_put_contents(
            BP . '/var/log/merlin_paypal_debug.log',
            "REQUEST SLICE:\n" . print_r($interesting, true) . "\n----------------------\n",
            FILE_APPEND
        );

        return isset($result) ? $result : null;
    }

    public function callSetExpressCheckout()
    {
        @file_put_contents(
            BP . '/var/log/merlin_paypal_debug.log',
            "BEFORE callSetExpressCheckout _exportLineItems map:\n" . print_r($this->_lineItemTotalExportMap, true) . "\n",
            FILE_APPEND
        );

        return parent::callSetExpressCheckout();
    }

    public function call($methodName, array $request)
    {
        $interesting = [];
        foreach ($request as $k => $v) {
            if (
                str_starts_with((string)$k, 'PAYMENTREQUEST_')
                || str_starts_with((string)$k, 'L_PAYMENTREQUEST_')
                || in_array((string)$k, ['METHOD', 'VERSION', 'AMT', 'ITEMAMT', 'TAXAMT', 'SHIPPINGAMT'], true)
            ) {
                $interesting[$k] = $v;
            }
        }

        @file_put_contents(
            BP . '/var/log/merlin_paypal_debug.log',
            "FINAL NVP REQUEST ({$methodName}):\n" . print_r($interesting, true) . "\n----------------------\n",
            FILE_APPEND
        );

        return parent::call($methodName, $request);
    }

    private function getQuoteValue(string $field): mixed
    {
        try {
            if ($this->_cart && method_exists($this->_cart, 'getSalesModel')) {
                $salesModel = $this->_cart->getSalesModel();
                if ($salesModel) {
                    return $salesModel->getDataUsingMethod($field);
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }
}
