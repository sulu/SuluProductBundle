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
            this.prices = groupPrices.call(this, this.options.data);

            this.sandbox.emit(INITIALIZED.call(this));
        }
    };
});
