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

    'use strict';

    var defaults = {
            instanceName: null,
            currencies: null,
            data: [],
            translations: {}
        },

        templates = {
          bulkPrice: function(idSuffix){
              return '<div id="bulk-price-'+idSuffix+'"></div>';
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
            if(!!this.options.data && this.options.data.length > 0) {
                this.groupedPrices = groupPrices.call(this, this.options.data);
            }

            this.initializeBulkPriceComponents();

            this.sandbox.emit(INITIALIZED.call(this));
        },

        /**
         * Initializes the subcomponents which will display the bulk prices for each currency
         */
        initializeBulkPriceComponents: function() {
            var bulkPriceComponents = [];

            this.sandbox.util.foreach(this.options.currencies, function(currency){
                var $el = this.sandbox.dom.createElement(templates.bulkPrice(currency.code)),
                    options = {
                        el: $el,
                        data: !!this.groupedPrices[currency.code] ? this.groupedPrices[currency.code] : [],
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
