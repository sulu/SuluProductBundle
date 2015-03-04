/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
/**
 * @class bulk-price@suluproduct
 * @constructor
 *
 * @param {Object} [options] Configuration object
 * @param {Array}  [options.data] Array of data [object]
 * @param {Array}  [options.instanceName] string instance name
 */
define(['text!suluproduct/components/bulk-price/bulk-price.html'], function(BulkPriceTemplate) {

    'use strict';

    var defaults = {
            instanceName: null,
            data: [],
            translations: {}
        },

        constants = {
            bulkPriceIdPrefix: 'bulk-price-'
        },

        eventNamespace = 'sulu.products.bulk-price.',

        /** returns normalized event names */
        createEventName = function(postFix) {
            return eventNamespace + (this.options.instanceName ? this.options.instanceName + '.' : '') + postFix;
        },

        /**
         * @event sulu.products.bulk-price.initialized
         * @description Emitted when component is initialized
         */
        INITIALIZED = function() {
            return createEventName.call(this, 'initialized');
        },

        /**
         * Returns the sales price (price with minimum quantity 0)
         * @param prices
         * @returns price
         */
        getSalesPrice = function(prices) {
            var salesPrice = null,
                idx = null;

            this.sandbox.util.foreach(prices, function(price, index) {
                if (parseFloat(price.minimumQuantity) === 0) {
                    salesPrice = price;
                    idx = index;
                    return false;
                }
            }.bind(this));

            // remove sales price
            if (idx !== null) {
                prices.splice(idx, 1);
            }
            return salesPrice;
        };

    return {

        initialize: function() {
            var prices, salesPrice;

            this.options = this.sandbox.util.extend({}, defaults, this.options);
            prices = this.sandbox.util.extend([], this.options.data);
            salesPrice = getSalesPrice.call(this, prices);
            this.render(prices, salesPrice);

            this.sandbox.emit(INITIALIZED.call(this));
        },

        render: function(prices, salesPrice) {
            var data = {
                    idPrefix: constants.bulkPriceIdPrefix,
                    currency: prices[0].currency,
                    salesPrice: salesPrice,
                    translate: this.sandbox.translate,
                    prices: prices
                },
                $el = this.sandbox.util.template(BulkPriceTemplate, data);
            this.sandbox.dom.append(this.options.el, $el);
        }
    };
});
