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

        constants = {
            invalidInputTranslation: 'products.price-calculation.invalid.input'
        },

        /**
         * Checks if a value is greater or equals zero
         * @param {Object} sandbox
         * @param {String|Number} value
         * @returns {boolean}
         */
        isGreaterThanOrEqualsZero = function(sandbox, value) {
            if (!!sandbox.dom.isNumeric(value)) {
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
            if (!!sandbox.dom.isNumeric(taxRate)) {
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
            if (!!sandbox.dom.isNumeric(discount)) {
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
            return locale !== 'en';
        },

        /**
         * Checks if price, taxrate, discount, amount have valid values
         * @param sandbox
         * @param price
         * @param taxRate
         * @param discount
         * @param amount
         */
        validCalculationParams = function(sandbox, price, taxRate, discount, amount) {
            if (!isGreaterThanOrEqualsZero(sandbox, price) ||
                !isGreaterThanOrEqualsZero(sandbox, taxRate) ||
                !isGreaterThanOrEqualsZero(sandbox, discount) ||
                !isGreaterThanOrEqualsZero(sandbox, amount)
            ) {
                sandbox.logger.error('Invalid parameter(s) for price calculation!');
                return false;
            }

            return true;
        },

        /**
         * Processes elements for getTotalPricesAndTaxes
         * @param sandbox
         * @param items
         * @param taxes
         * @param netPrice
         * @param grossPrice
         */
        processPriceCalculationItem = function(sandbox, items) {
            var tax = 0, i, item,
                result = {
                    taxes: {},
                    netPrice: 0,
                    grossPrice: 0
                };

            for (i in items) {
                if (isGreaterThanOrEqualsZero(sandbox, items[i].price) && isGreaterThanOrEqualsZero(sandbox, items[i].tax)) {
                    item = items[i];
                    result.netPrice += parseFloat(item.price);
                    tax = item.price * getTaxRate(sandbox, item.tax);
                    if (tax > 0) {
                        if (!!result.taxes[item.tax]) {
                            result.taxes[item.tax] += tax;
                        } else {
                            result.taxes[item.tax] = tax;
                        }
                    }
                    result.grossPrice += item.price + tax;
                } else {
                    sandbox.logger.error('Invalid parameter(s) for price calculation!');
                    throw new Error(sandbox.translate(constants.invalidInputTranslation));
                }
            }

            return result;
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
        getFormattedGrossPrice: function(sandbox, price, currency, taxRate) {
            if (!validCalculationParams(sandbox, price, taxRate, 0, 0)) {
                return sandbox.translate(constants.invalidInputTranslation);
            }

            var total, locale;

            price = parseFloat(price);
            taxRate = getTaxRate(sandbox, taxRate);
            currency = currency || defaults.currency;
            locale = sandbox.globalize.getLocale() || defaults.locale;
            total = price + (price * taxRate);

            return this.getFormattedNumberWithAddition(sandbox, total, currency, appendCurrencyToPrice(locale));
        },

        /**
         * Returns formatted net price
         * @param {Object} sandbox
         * @param {Number} price gross
         * @param {String} currency
         * @param {Number} taxRate
         * @return {String} formatted price including currency
         */
        getFormattedNetPrice: function(sandbox, price, currency, taxRate) {

            if (!validCalculationParams(sandbox, price, taxRate, 0, 0)) {
                return sandbox.translate(constants.invalidInputTranslation);
            }

            var total, locale;

            price = parseFloat(price);
            taxRate = getTaxRate(sandbox, taxRate);
            currency = currency || defaults.currency;
            locale = sandbox.globalize.getLocale() || defaults.locale;
            total = price - (price * taxRate);

            return this.getFormattedNumberWithAddition(sandbox, total, currency, appendCurrencyToPrice(locale));
        },

        /**
         * Formats an amount of something and adds an addition (unit, currency, ...)
         * @param {Object} sandbox
         * @param {Number} amount
         * @param {String} unit
         * @return {String} formatted string
         */
        getFormattedAmountAndUnit: function(sandbox, amount, unit) {
            if (!isGreaterThanOrEqualsZero(sandbox, amount)) {
                sandbox.logger.error('Invalid parameter in getFormattedAmountAndUnit!');
                return sandbox.translate(constants.invalidInputTranslation);
            }

            unit = unit || defaults.unit;
            return this.getFormattedNumberWithAddition(sandbox, amount, unit, true);
        },

        /**
         * Will format a number and append or prepend the addition
         * @param sandbox
         * @param value
         * @param addition
         * @param {Boolean} append addition if true else prepend
         * @returns {String}
         */
        getFormattedNumberWithAddition: function(sandbox, value, addition, append) {
            var formatted = sandbox.numberFormat(value, 'n');

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
         * @param amount
         * @param {Number} taxRate
         * @param {Boolean} isNetPrice
         */
        getTotalPrice: function(sandbox, price, currency, discount, amount, taxRate, isNetPrice) {
            if (!validCalculationParams(sandbox, price, taxRate, discount, amount)) {
                return sandbox.translate(constants.invalidInputTranslation);
            }

            var total, locale;

            price = parseFloat(price);
            amount = parseFloat(amount);
            taxRate = getTaxRate(sandbox, taxRate);
            currency = currency || defaults.currency;
            discount = getDiscount(sandbox, discount);
            locale = sandbox.globalize.getLocale() || defaults.locale;

            if (!isNetPrice) {
                price = price - (price * taxRate);
            }

            total = price * amount;
            total = total - (total * discount);

            return this.getFormattedNumberWithAddition(sandbox, total, currency, appendCurrencyToPrice(locale));
        },

        /**
         * Sums up all prices to a total net price, calculates a total tax amount per tax class/rate
         * and returns the taxes, a total net and a total gross price
         * @param {Object} sandbox
         * @param {Object} items
         * [
         *  {
         *   price: 100,
         *   taxRate: 20
         *  }
         * ]
         *
         * @return {Object}
         */
        getTotalPricesAndTaxes: function(sandbox, items) {

            if (!!items) {
                try {
                    return processPriceCalculationItem.call(this, sandbox, items);
                } catch (ex) {
                    return null;
                }
            }

            return null;
        }
    };
});
