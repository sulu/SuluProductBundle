/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
/**
 * @class price-list@suluproduct
 * @constructor
 *
 * @param {Object} [options] Configuration object
 * @param {Array}  [options.data] Array of data [object]
 * @param {Array}  [options.instanceName] string instance name
 */
define([], function() {

    // TODO Validation

    'use strict';

    var defaults = {
            instanceName: null,
            currencies: null,
            data: [],
            translations: {}
        },

        templates = {
          bulkPrice: function(idSuffix){
              return '<div id="bulk-price-'+idSuffix+'"><form id="prices-form"></form></div>';
          }
        },

        eventNamespace = 'sulu.products.price-list.',

        /** returns normalized event names */
        createEventName = function(postFix) {
            return eventNamespace + (this.options.instanceName ? this.options.instanceName + '.' : '') + postFix;
        },

        /**
         * @event sulu.products.price-list.initialized
         * @description Emitted when component is initialized
         */
        INITIALIZED = function() {
            return createEventName.call(this, 'initialized');
        },

        bindCustomEvents = function(){
            this.sandbox.on('sulu.products.bulk-price.changed', function(){
                updateData.call(this);
            }, this);
        },

        updateData = function(){
            // get all items
            var $bulkPrices = this.sandbox.dom.children(this.$el),
                pricesData = [], prices, specialPrice, specialPriceData = [];

            this.sandbox.util.foreach($bulkPrices, function($bulkPrice){
                prices = this.sandbox.dom.data($bulkPrice, 'items');
                specialPrice = $.data($bulkPrice, 'itemsSpecialPrice');
                Array.prototype.push.apply(pricesData, prices);
                Array.prototype.push.call(specialPriceData, specialPrice);
            }.bind(this));

            // set properties
            this.sandbox.dom.data(this.$el, 'prices', pricesData);
            var specialPricesEl = $('#specialPrices');
            this.sandbox.dom.data(specialPricesEl, 'prices', specialPriceData);
        },

        placeDefaultCurrencyFirst = function(defaultCur, currencies){
            var tmp = null,
                idxDefault = null;
            this.sandbox.util.foreach(currencies, function(cur, index){
                if(cur.code === defaultCur){
                    idxDefault = index;
                    return false;
                }
            }.bind(this));

            // when index of default currency is set and it is not already on the first position
            if(!!idxDefault) {
                tmp = currencies[0];
                currencies[0] = currencies[idxDefault];
                currencies[idxDefault] = tmp;
            }
        },

        groupPrices = function(prices){
            var groups = [];
            this.sandbox.util.foreach(prices, function(price){
                if(!groups[price.currency.code]){
                    groups[price.currency.code] = [];
                }
                groups[price.currency.code].push(price);
            }.bind(this));

            return groups;
        };

    return {

        initialize: function() {
            this.options = this.sandbox.util.extend({}, defaults, this.options);
            this.groupedPrices = [];
            if(!!this.options.data.prices && this.options.data.prices.length > 0) {
                this.groupedPrices.prices = groupPrices.call(this, this.options.data.prices);
                placeDefaultCurrencyFirst.call(this, this.options.defaultCurrency, this.options.currencies);
            }

            if(!!this.options.data.specialPrices && this.options.data.specialPrices.length > 0) {
                this.groupedPrices.specialPrices = groupPrices.call(this, this.options.data.specialPrices);
            }

            bindCustomEvents.call(this);

            this.initializeBulkPriceComponents();

            this.sandbox.emit(INITIALIZED.call(this));
        },

        /**
         * Initializes the subcomponents which will display the bulk prices for each currency
         */
        initializeBulkPriceComponents: function() {
            var bulkPriceComponents = [];

            this.sandbox.util.foreach(this.options.currencies, function(currency){

                this.bulkPriceData = [];

                this.bulkPriceData.currencyCode = currency.code;

                if (!!this.groupedPrices.prices) {
                    this.bulkPriceData.prices = this.groupedPrices.prices[currency.code];
                }
             
                if (!!this.groupedPrices.specialPrices && currency.code in this.groupedPrices.specialPrices) {
                    this.bulkPriceData.specialPrice = this.groupedPrices.specialPrices[currency.code].pop();
                }

                var $el = this.sandbox.dom.createElement(templates.bulkPrice(currency.code)),
                    options = {
                        el: $el,
                        data: this.bulkPriceData,
                        instanceName: currency.code,
                        currency: currency
                    };
                bulkPriceComponents.push({name: 'bulk-price@suluproduct', options: options});
                this.sandbox.dom.append(this.options.el, $el);
            }.bind(this));

            this.sandbox.start(bulkPriceComponents);
        }
    };
});
