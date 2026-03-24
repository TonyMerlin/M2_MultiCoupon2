define(['jquery'], function ($) {
    'use strict';

    return function (config, element) {
        var $root = $(element);
        var $input = $root.find('#merlin_multi_coupon_code');

        if ($input.length) {
            $input.on('input', function () {
                this.value = this.value.toUpperCase().replace(/\s+/g, '');
            });
        }
    };
});
