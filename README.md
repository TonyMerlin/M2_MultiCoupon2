Merlin_MultiCoupon

Magento 2 module to allow a controlled set of coupon codes to be used together in a single cart, with each code applying only to the products it is valid for.

This module was built for the DEAL coupon family:

DEAL5

DEAL10

DEAL15

DEAL20

DEAL25

It replaces the standard single-code cart coupon workflow with a multi-code cart form and applies the best valid matching deal per item.

What this module does

Magento natively supports only one coupon code on a quote. This module extends the cart workflow so multiple approved coupon codes can be stored on the quote and processed together.

Example:

Product A qualifies for DEAL10

Product B qualifies for DEAL20

The customer can enter both codes and complete checkout in a single order. Each product receives the correct discount for its own matching rule.

Key behaviour

Supports multiple coupon codes on one quote

Restricts usage to approved deal codes only

Rejects codes that do not apply to any current basket item

Prevents duplicate code entry

Applies the best single matching deal per item

Persists applied multi-codes to the order

Removes Magento’s standard cart coupon box so customers use the multi-code form instead

How discounts are applied

For each entered code, the module:

Normalizes the code

Confirms the code is allowed

Loads the linked sales rule

Checks whether the rule applies to at least one visible basket item

Stores the code on the quote if applicable

Recollects totals

During totals collection, evaluates all stored codes against each item

Applies the highest valid discount for that item

This means:

one item will not be discounted multiple times by overlapping deal codes

different items in the same basket can receive different deal codes

non-applicable codes are rejected at add time with a clear message

Supported discount types

This module currently supports the following Magento cart price rule action types:

by_percent

by_fixed

cart_fixed

It is intended for straightforward deal-code scenarios where rules are product-based.

Features
Multi-code cart form

Customers can:

add a code

remove an individual code

clear all codes

Quote and order persistence

The module stores multi-codes in custom fields on:

quote

sales_order

Custom totals collector

A custom quote total processes all stored codes and applies the correct discount amounts to matching items.

Basket applicability validation

Codes are rejected if they do not apply to any product currently in the basket.

Native cart coupon removal

The standard Magento single discount code box is removed from the cart page so customers only use the multi-code workflow.

Module structure

Typical key files:

app/code/Merlin/MultiCoupon/
├── registration.php
├── etc/
│   ├── module.xml
│   ├── db_schema.xml
│   ├── di.xml
│   └── sales.xml
├── Controller/Cart/
│   ├── AddCoupon.php
│   ├── RemoveCoupon.php
│   └── ClearCoupons.php
├── Model/
│   ├── Config.php
│   ├── QuoteCouponStorage.php
│   ├── RuleRepository.php
│   └── Discount/
│       ├── MultiCoupon.php
│       ├── ItemRuleMatcher.php
│       └── Calculator.php
└── view/frontend/
    ├── layout/
    │   └── checkout_cart_index.xml
    └── templates/
Requirements

Magento 2.4.x

Existing cart price rules and coupon codes already configured in Magento Admin

Rules created for the supported deal codes

Installation

Copy the module into:

app/code/Merlin/MultiCoupon

Then run:

bin/magento module:enable Merlin_MultiCoupon
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush

If needed in production mode:

rm -rf generated/code/* generated/metadata/* var/view_preprocessed/*
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
Database changes

This module adds custom storage fields for the multi-coupon string.

Typical fields:

quote.merlin_multi_coupon_codes

sales_order.merlin_multi_coupon_codes

These fields store the applied codes as a comma-separated list, for example:

DEAL10,DEAL20
Configuration

The module uses an internal allowed-code list for the approved deal coupons.

Typical allowed codes:

DEAL5

DEAL10

DEAL15

DEAL20

DEAL25

Only these codes are accepted by the custom add-code controller.

Cart workflow
Adding a code

When a customer enters a code:

the code is normalized

the code must be in the allowed list

the related Magento sales rule must load successfully

at least one visible cart item must match the rule

if valid, the code is added to the quote and totals are recollected

Removing a code

An individual stored code can be removed from the quote, then totals are recollected.

Clearing all codes

All stored multi-codes can be removed in one action.

Totals behaviour

The module registers a custom quote total and applies it in the quote totals flow.

Important points:

totals are recollected after code add/remove/clear

the custom collector applies the best valid single code per item

discount amounts are reflected in cart totals

tax behaviour follows the configured rule and item total basis used by the module

Native coupon box removal

The standard Magento cart coupon block is removed via layout XML.

Typical layout removal:

<referenceBlock name="checkout.cart.coupon" remove="true"/>

This ensures customers use the multi-code form instead of Magento’s standard single-code field.

Example scenario

Basket contains:

Product 1 eligible for DEAL10

Product 2 eligible for DEAL20

Customer enters:

DEAL10

DEAL20

Result:

Product 1 receives the DEAL10 discount

Product 2 receives the DEAL20 discount

customer completes checkout in one order

If the customer enters DEAL25 and no basket item qualifies, the module rejects it with a message such as:

Coupon code "DEAL25" does not apply to any products currently in your basket.
Testing checklist

Recommended tests:

Add one product eligible for DEAL10, then add DEAL10

Add one product eligible for DEAL20, then add DEAL20

Add products eligible for different deal codes, then apply both

Try to add a valid but non-applicable deal code and confirm rejection

Remove one code and confirm totals recalculate correctly

Clear all codes and confirm totals return to normal

Place an order and confirm the stored multi-code field is copied to the order

Known scope

This module is designed for a controlled multi-deal implementation. It is not intended to be a full generic multi-coupon engine for every Magento coupon scenario.

Current design assumptions:

coupon usage is restricted to approved deal codes

rules are product-oriented

best single valid deal is applied per item

basket applicability is checked when adding codes

Troubleshooting
Code saves but no discount appears

Check:

the Magento sales rule exists and is active

the code is in the allowed-code list

the product actually matches the rule

totals are being recollected after add/remove/clear

Code is rejected as not allowed

Check:

code spelling

allowed-code configuration in the module

normalization logic in Config.php

Code is allowed but rejected as non-applicable

Check:

the current basket contents

the rule actions/conditions

whether the product truly matches the rule in Magento native behaviour

Totals differ from native Magento coupon behaviour

Check:

sales rule action type

tax/discount basis

collector sort order in etc/sales.xml

calculation logic in Model/Discount/Calculator.php

Developer notes

Main classes:

Config.php
Normalizes codes and controls which codes are allowed

QuoteCouponStorage.php
Reads/writes stored multi-codes on the quote

RuleRepository.php
Loads the underlying Magento sales rule for a code

ItemRuleMatcher.php
Determines whether a quote item matches a rule

Calculator.php
Calculates discount amount for a matching item/rule pair

MultiCoupon.php
Custom quote total collector that processes all stored codes

Controller/Cart/AddCoupon.php
Adds a code only if it is allowed and applies to at least one visible basket item

Controller/Cart/RemoveCoupon.php
Removes one code and recollects totals

Controller/Cart/ClearCoupons.php
Clears all codes and recollects totals

Uninstall

To remove the module:

bin/magento module:disable Merlin_MultiCoupon

Then remove the code from:

app/code/Merlin/MultiCoupon

If you also want to remove database fields, create and run a proper schema removal or uninstall script appropriate for your deployment process.

Notes

This module is intended for controlled multi-deal retail promotions where a small number of known coupon codes must work together in one basket without allowing arbitrary coupon stackin
