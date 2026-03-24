define([
    'uiComponent',
    'ko',
    'Magento_Checkout/js/model/quote'
], function (Component, ko, quote) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Merlin_MultiCoupon/cart/coupons'
        },

        initialize: function () {
            this._super();
            this.codes = ko.observableArray(
                (window.checkoutConfig && window.checkoutConfig.merlinMultiCoupon && window.checkoutConfig.merlinMultiCoupon.codes) || []
            );
            this.allowedCodes = ko.observableArray(
                (window.checkoutConfig && window.checkoutConfig.merlinMultiCoupon && window.checkoutConfig.merlinMultiCoupon.allowedCodes) || []
            );

            return this;
        },

        hasCodes: function () {
            return this.codes().length > 0;
        },

        getQuoteId: function () {
            return quote.getQuoteId ? quote.getQuoteId() : null;
        }
    });
});
