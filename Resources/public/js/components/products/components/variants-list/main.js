/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define([
    'suluproduct/collections/currencies',
    'suluproduct/models/variant'
], function(Currencies, Variant) {

    'use strict';

    var constants = {},

        /**
         * Starts toolbar and datagrid components.
         */
        startComponents = function() {
            this.sandbox.sulu.initListToolbarAndList.call(
                this,
                'product-variants-list',
                '/admin/api/product-variants/fields',
                {
                    el: '#list-toolbar',
                    instanceName: constants.toolbarInstanceName,
                    small: false,
                    template: this.sandbox.sulu.buttons.get(
                        {
                            add: {
                                options: {
                                    id: 'add',
                                    icon: 'plus-circle',
                                    callback: openAddOverlay.bind(this)
                                }
                            },
                            delete: {
                                options: {
                                    id: 'delete',
                                    icon: 'trash-o',
                                    disabled: true,
                                    callback: onDeleteClicked.bind(this)
                                }
                            }
                        }
                    )
                },
                {
                    el: '#product-variants',
                    actionCallback: onVariantClicked.bind(this),
                    resultKey: 'products',
                    searchFields: ['name'],
                    url: '/admin/api/products/' + this.options.data.id + '/variants?flat=true&locale=' +
                    this.options.locale
                }
            );
        },

        /**
         * Bind custom events.
         */
        bindCustomEvents = function() {
            // TODO: Header for saving product status.
            // TODO: Delete Variants.

            //this.sandbox.on('sulu.toolbar.save', save.bind(this));
            //this.sandbox.on('sulu.products.saved', function(model) {
            //        this.options.data = model;
            //        this.status = model.status;
            //        this.saved = true;
            //        this.sandbox.emit(
            //            'husky.toolbar.' + constants.toolbarInstanceName + '.item.disable',
            //            'save-button',
            //            false
            //        );
            //    }, this
            //);

            this.sandbox.on('sulu.product-variant-overlay.closed', onCloseVariantOverlay.bind(this));
        },

        /**
         * Called when variant-overlay gets closed.
         */
        onCloseVariantOverlay = function() {
            this.sandbox.stop(this.$overlay);
        },

        /**
         * Renders the variants list.
         */
        render = function() {
            this.sandbox.dom.html(this.$el, this.renderTemplate('/admin/product/template/product/variants'));
            startComponents.call(this);
        },

        /**
         * Callback when delete in toolbar is clicked.
         */
        onDeleteClicked = function() {
            alert('Please delete an attribute');
        },

        /**
         * Callback when datagrid row is clicked.
         *
         * @param {Number} id
         * @param {Object} data
         */
        onVariantClicked = function(id, data) {
            openAddOverlay.call(this, data);
        },

        /**
         * Function loads currencies from api.
         *
         * @returns {Array}
         */
        loadCurrencies = function() {
            var deferred = $.Deferred();

            var currencies = new Currencies({locale: this.options.locale});
            currencies.fetch({
                success: function(data) {
                    this.currencies = data.toJSON();
                    deferred.resolve();
                }.bind(this),
                error: function() {
                    console.error('Error while loading currencies');
                    deferred.reject();
                }
            });

            return deferred.promise();
        },

        /**
         * Called when data triggered by variant-overlay.
         *
         * @param {Object} data
         * @param {String} locale
         */
        onVariantSaved = function(data, locale) {
            var variant = Variant.findOrCreate({
                id: data.id,
                productId: this.options.data.id,
                locale: locale
            });
            variant.save(data, {
                success: function() {
                    // TODO: Update datagrid.
                }
            });
        },

        /**
         * Starts the overlay component for adding new variants.
         */
        openAddOverlay = function(data) {
            // Only open overlay, once currencies are loaded.
            this.currenciesLoaded.then(function() {
                // Create container for overlay.
                this.$overlay = this.sandbox.dom.createElement('<div>');
                this.sandbox.dom.append(this.$el, this.$overlay);

                // Create content.
                this.sandbox.start([{
                    name: 'variant-overlay@suluproduct',
                    options: {
                        el: this.$overlay,
                        data: data,
                        currencies: this.currencies,
                        locale: this.options.locale,
                        parentPrices: this.options.data.attributes.prices,
                        variantAttributes: this.options.data.attributes.variantAttributes,
                        okCallback: onVariantSaved.bind(this)
                    }
                }]);
            }.bind(this));
        };

    return {
        view: true,

        templates: ['/admin/product/template/product/variants'],

        initialize: function() {
            this.$overlay = null;
            this.currencies = [];

            this.currenciesLoaded = loadCurrencies.call(this);

            render.call(this);

            bindCustomEvents.call(this);
        }
    };
});
