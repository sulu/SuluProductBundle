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
            translations: {},
            currency: null
        },

        constants = {
            maxBulkElements: 4,
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
         * Returns the sales price (price with minimum quantity 0) and formats prices according locale
         * @param prices
         * @returns price
         */
        getSalesPrice = function(prices) {
            var salesPrice = null,
                idx = null;

            this.sandbox.util.foreach(prices, function(price, index) {
                if (parseFloat(price.minimumQuantity) === 0 && idx === null) {
                    salesPrice = price;
                    idx = index;
                }

                price.minimumQuantity = (!!price.minimumQuantity || price.minimumQuantity === 0) ?
                    this.sandbox.numberFormat(parseFloat(price.minimumQuantity), 'n') : '';
                price.price = (!!price.price || price.price === 0) ?
                    this.sandbox.numberFormat(price.price, 'n') : '';

            }.bind(this));

            // remove sales price
            if (idx !== null) {
                prices.splice(idx, 1);
            }
            return salesPrice;
        },

        addEmptyObjects = function(prices) {
            var i = prices.length;

            if (i < constants.maxBulkElements) {
                for (; i < constants.maxBulkElements; i++) {
                    prices.push({});
                }
            }

            return prices;
        },

        bindCustomEvents = function() {
            this.sandbox.on('sulu.products.bulk-price.get-data', function() {

            }, this);
        },

        bindDomEvents = function() {
            this.sandbox.dom.on(this.$el, 'keyup', function(event) {
                // TODO emit changed event?
            }.bind(this), 'input');
        },

        getData = function(event, data) {
            //var $tr = this.sandbox.dom.closest(event.currentTarget, 'tr'),
            //    id = this.sandbox.dom.data($tr, 'id'),
            //    target = this.sandbox.dom.data(event.currentTarget, 'property'),
            //    value = this.sandbox.dom.val(event.currentTarget),
            //    priceIdx = null;
            //
            //this.sandbox.util.foreach(data, function(price, idx) {
            //    if (price.id === id) {
            //        priceIdx = idx;
            //        return false;
            //    }
            //}.bind(this));
            //
            //if (!!priceIdx) {
            //
            //} else {
            //
            //}
        };

    return {

        initialize: function() {
            var prices = [], salesPrice;

            this.options = this.sandbox.util.extend({}, defaults, this.options);
            if (!!this.options.data) {
                prices = this.sandbox.util.extend([], this.options.data);
                salesPrice = getSalesPrice.call(this, prices);
            }

            prices = addEmptyObjects.call(this, prices);
            bindCustomEvents.call(this);
            bindDomEvents.call(this);

            this.render(prices, salesPrice);
            this.sandbox.emit(INITIALIZED.call(this));
        },

        render: function(prices, salesPrice) {
            var data = {
                    idPrefix: constants.bulkPriceIdPrefix,
                    currency: this.options.currency,
                    salesPrice: salesPrice,
                    translate: this.sandbox.translate,
                    prices: prices
                },
                $el = this.sandbox.util.template(BulkPriceTemplate, data);
            this.sandbox.dom.append(this.options.el, $el);
        }
    };
});
