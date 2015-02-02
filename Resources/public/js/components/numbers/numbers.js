/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define([], function() {

    'use strict';

    // TODO inject default values at server when implemented
    // Defaults should include taxes, currency, unit, locale
    var defaults = {
            currency: 'EUR',
            taxRate: 0.20,
            locale: 'en',
            unit: 'pc'
        },

        /**
         * Checks if a value is greater or equals zero
         * @param {Object} sandbox
         * @param {String|Number} value
         * @returns {boolean}
         */
        isGreaterThanOrEqualsZero = function(sandbox, value) {
            if (!!value && sandbox.dom.isNumeric(value)) {
                value = parseFloat(value);
                return !!(!isNaN(value) && value >= 0);
            }
            return false;
        },

        /**
         * Returns taxrate as float value between 0 and 1
         * @param {Object} sandbox
         * @param {Number} taxRate
         * @returns {Number}
         */
        getTaxRate = function(sandbox, taxRate) {
            if (!!taxRate && sandbox.dom.isNumeric(taxRate)) {
                return parseFloat(taxRate) / 100;
            } else {
                return defaults.taxRate;
            }
        },

        /**
         * Returns discount as float value between 0 and 1
         * @param {Object} sandbox
         * @param {Number} discount
         * @returns {Number}
         */
        getDiscount = function(sandbox, discount) {
            if (!!discount && sandbox.dom.isNumeric(discount)) {
                return parseFloat(discount) / 100;
            } else {
                return 0;
            }
        },

        /**
         * Decides if currency should be appended or prepended
         * @param locale
         * @returns {boolean}
         */
        appendCurrencyToPrice = function(locale) {
            // TODO ???
            return locale !== 'en';
        },

        /**
         * Checks if price and taxrate have valid values
         * @param sandbox
         * @param price
         * @param taxRate
         * @param discount
         */
        validCalculationParams = function(sandbox, price, taxRate, discount) {
            discount = discount || 0;
            if (isGreaterThanOrEqualsZero(sandbox, price) ||
                isGreaterThanOrEqualsZero(sandbox, taxRate) ||
                isGreaterThanOrEqualsZero(sandbox, discount)
            ) {
                sandbox.logger.error('Invalid parameter(s) for price calculation!');
            }
        },

        /**
         * Processes elements for getTotalPricesAndTaxes
         * @param sandbox
         * @param el
         * @param taxes
         * @param netPrice
         * @param grossPrice
         */
        processPriceForTaxClass = function(sandbox, el, taxes, netPrice, grossPrice) {

            if (isGreaterThanOrEqualsZero(sandbox, el.taxRate)) {
                var currentTaxRate = getTaxRate(sandbox, el.taxRate),
                    tax = 0;

                sandbox.util.foreach(el.prices, function(price) {
                    if (isGreaterThanOrEqualsZero(sandbox, price)) {
                        netPrice += parseFloat(price);
                        tax = price * currentTaxRate;
                        taxes[currentTaxRate] += tax;
                        grossPrice += ((price * tax) + price);
                    } else {
                        // TODO ???
                    }
                }.bind(this));
            }
        };

    return {

        /**
         * Returns formatted gross price
         * @param {Object} sandbox
         * @param {Number} price net
         * @param {String} currency
         * @param {Number} taxRate percentage value
         * @return {String} formatted price including currency
         */
        getFormatedGrossPrice: function(sandbox, price, currency, taxRate) {

            if (validCalculationParams(sandbox, price, taxRate)) {
                return 'Invalid price or taxrate!';
            }

            var total, locale;

            price = parseFloat(price);
            taxRate = getTaxRate(sandbox, taxRate);
            currency = currency || defaults.currency;
            locale = this.sandbox.globalize.getLocale() || defaults.locale;
            total = price + (price * taxRate);

            return this.getFormattedNumberWithAddition(total, currency, appendCurrencyToPrice(locale));
        },

        /**
         * Returns formatted net price
         * @param {Object} sandbox
         * @param {Number} price gross
         * @param {String} currency
         * @param {Number} taxRate
         * @return {String} formatted price including currency
         */
        getFormatedNetPrice: function(sandbox, price, currency, taxRate) {

            if (validCalculationParams(sandbox, price, taxRate)) {
                return 'Invalid price or taxrate!';
            }

            var total, locale;

            price = parseFloat(price);
            taxRate = getTaxRate(sandbox, taxRate);
            currency = currency || defaults.currency;
            locale = this.sandbox.globalize.getLocale() || defaults.locale;
            total = price - (price * taxRate);

            return this.getFormattedNumberWithAddition(total, currency, appendCurrencyToPrice(locale));
        },

        /**
         * Formats an amount of something and adds an addition (unit, currency, ...)
         * @param {Object} sandbox
         * @param {Number} amount
         * @param {String} unit
         * @return {String} formatted string
         */
        getFormatedAmountAndUnit: function(sandbox, amount, unit) {
            if (isGreaterThanOrEqualsZero(amount)) {
                sandbox.logger.error('Invalid parameter in getFormatedAmountAndUnit!');
                return 'Invalid price or taxrate!';
            }

            unit = unit || defaults.unit;
            return this.getFormattedNumberWithAddition(amount, unit, true);
        },

        /**
         * Will format a number and append or prepend the addition
         * @param value
         * @param addition
         * @param {Boolean} append addition if true else prepend
         * @returns {String}
         */
        getFormattedNumberWithAddition: function(value, addition, append) {
            var formatted = this.sandbox.numberFormat(value);

            if (!append) {
                return addition + '' + formatted;
            } else {
                return formatted + ' ' + addition;
            }
        },

        /**
         * Calculates a price from a price and subtracts a discount from the net price
         * @param {Object} sandbox
         * @param {Number} price gross or net
         * @param {String} currency percentage value
         * @param {Number} discount
         * @param {Number} taxRate
         * @param {Boolean} isNetPrice
         */
        getTotalPrice: function(sandbox, price, currency, discount, taxRate, isNetPrice) {
            if (validCalculationParams(sandbox, price, taxRate, discount)) {
                return 'Invalid price or taxrate or discount!';
            }

            var total, locale;

            price = parseFloat(price);
            taxRate = getTaxRate(sandbox, taxRate);
            currency = currency || defaults.currency;
            discount = getDiscount(discount);
            locale = this.sandbox.globalize.getLocale() || defaults.locale;

            if (!isNetPrice) {
                total = price - (price * taxRate);
            }

            total = total - (total * discount);

            return this.getFormattedNumberWithAddition(total, currency, appendCurrencyToPrice(locale));
        },

        /**
         * Sums up all prices to a total net price, calculates a total tax amount per tax class/rate
         * and returns an overall total price including taxes
         * @param {Object} sandbox
         * @param {Object} values
         * {
         *  prices: [
         *   {
         *      price: 100,
         *      discount: 5
         *   }
         *  ]
         *  taxRate: 20
         * }
         *
         * @return {Object}
         */
        getTotalPricesAndTaxes: function(sandbox, values) {

            var netPrice = 0,
                taxes = {},
                grossPrice = 0,
                el;

            for (el in values) {
                if (values.hasOwnProperty(el)) {
                    processPriceForTaxClass.call(this, sandbox, el, taxes, netPrice, grossPrice);
                }
            }

            return {
                taxes: taxes,
                netPrice: netPrice,
                grossPrice: grossPrice
            };
        }
    };
});
