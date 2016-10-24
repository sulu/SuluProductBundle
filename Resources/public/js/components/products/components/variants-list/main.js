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

    var constants = {
            datagridInstanceName: 'variants',
            toolbarInstanceName: 'variants'
        },

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
                    instanceName: constants.datagridInstanceName,
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

            this.sandbox.on(
                'husky.datagrid.' + constants.datagridInstanceName + '.number.selections',
                onProductAttributeSelection.bind(this, constants.toolbarInstanceName)
            );

            this.sandbox.on('sulu.product-variant-overlay.closed', onCloseVariantOverlay.bind(this));
        },

        /**
         * Enables or disables toolbar based on number of items that were selected.
         *
         * @param {Number} number
         * @param {String} toolBarInstanceName
         */
        onProductAttributeSelection = function(toolBarInstanceName, number) {
            var postfix = number > 0 ? 'enable' : 'disable';
            this.sandbox.emit(
                'husky.toolbar.' + toolBarInstanceName + '.item.' + postfix,
                'delete',
                false)
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
            var selectedIds = [];
            this.sandbox.emit(
                'husky.datagrid.' + constants.datagridInstanceName + '.items.get-selected',
                function(ids) {
                    selectedIds = ids;
                }
            );

            var numberOfDeletions = selectedIds.length;
            if (numberOfDeletions < 1) {
                return;
            }

            var variant = new Variant({
                productId: this.options.data.id,
                ids: selectedIds
            });

            variant.destroy({
                success: onDeleteSuccess.bind(this),
                error: onDeleteError.bind(this)
            });
        },

        /**
         * Called when delete call was successful.
         */
        onDeleteSuccess = function() {
            updateDatagrid.call(this);
            this.sandbox.emit('sulu.labels.success.show', 'labels.success.delete-desc', 'labels.success');
            this.sandbox.emit('husky.datagrid.' + constants.datagridInstanceName + '.selected.update');
        },

        /**
         * Called when delete call returned an error.
         */
        onDeleteError = function() {
            this.sandbox.emit('sulu.labels.error.show', 'sulu_product.labels.delete-error', 'labels.error');
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
                    updateDatagrid.call(this);
                    this.sandbox.emit('sulu.labels.success.show', 'labels.success.save-desc', 'labels.success');
                }.bind(this),
                error: function() {
                    this.sandbox.emit('sulu.labels.error.show', 'sulu_product.labels.save-error', 'labels.error');
                }.bind(this)
            });
        },

        /**
         * Triggeres event to update datagrid.
         */
        updateDatagrid = function() {
            this.sandbox.emit('husky.datagrid.' + constants.datagridInstanceName + '.update');
        },

        /**
         * Starts the overlay component for adding new variants.
         */
        openAddOverlay = function(data) {
            // Only open overlay, onconce currencies are loaded.
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

        /**
         * Initialization function of variants-list.
         */
        initialize: function() {
            this.$overlay = null;
            this.currencies = [];
            this.currenciesLoaded = loadCurrencies.call(this);

            // Set correct status in header bar.
            this.status = this.options.data.attributes.status;
            this.sandbox.emit('product.state.change', this.status);

            render.call(this);

            bindCustomEvents.call(this);
        }
    };
});
