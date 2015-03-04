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
                if(!groups[price.currency.name]){
                    groups[price.currency.name] = [];
                }
                groups[price.currency.name].push(price);
            }.bind(this));
            return groups;
        };

    return {

        initialize: function() {
            this.options = this.sandbox.util.extend({}, defaults, this.options);
            this.groupedPrices = groupPrices.call(this, this.options.data);

            this.initializeBulkPriceComponents();

            this.sandbox.emit(INITIALIZED.call(this));
        },

        /**
         * Initiailzes the subcomponents which will display the bulk prices for each currency
         */
        initializeBulkPriceComponents: function() {
            var bulkPriceComponents = [],
                group;

            for (group in this.groupedPrices) {
                var $el = this.sandbox.dom.createElement(templates.bulkPrice(this.groupedPrices[group][0].currency.code)),
                    options = {
                        el: $el,
                        data: this.groupedPrices[group],
                        instanceName: this.groupedPrices[group][0].currency.code
                    };
                bulkPriceComponents.push({name: 'bulk-price@suluproduct', options: options});
                this.sandbox.dom.append(this.options.el, $el);
            }

            this.sandbox.start(bulkPriceComponents);
        }
    };
});
