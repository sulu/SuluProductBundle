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
        getSalesPrice = function(prices){
            var salesPrice = null;
            this.sandbox.util.foreach(prices, function(price){
                if(parseFloat(price.minimumQuantity) === 0) {
                    salesPrice = price;
                    return false;
                }
            }.bind(this));

            return salesPrice;
        };

    return {

        initialize: function() {
            this.options = this.sandbox.util.extend({}, defaults, this.options);
            this.salesPrice = getSalesPrice.call(this, this.options.data);

            this.render();

            this.sandbox.emit(INITIALIZED.call(this));
        },

        render: function(){
            var $el = this.sandbox.util.template(BulkPriceTemplate, {});
            this.sandbox.dom.append(this.options.el, $el);
        }
    };
});
