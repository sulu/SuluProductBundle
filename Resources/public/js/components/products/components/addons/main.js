/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define([
    'config',
    'suluproduct/models/product-addon',
    'text!suluproduct/components/products/components/addons/overlay.html',
    'text!suluproduct/components/products/components/addons/price.html',
    'services/product-type-manager'
], function(Config, ProductAddon, OverlayTemplate, PriceTemplate, ProductTypeManager) {

    'use strict';

    var currencies = null,
        currentSelectedAddon = null,
        currentProductAddon = null,

        constants = {
            datagridInstanceName: 'product-addon-datagrid',
            toolbarInstanceName: 'product-addon-toolbar',
            overlayInstanceName: 'product-addon-overlay',
            selectInstanceName: 'product-addon-select',
            priceListElementId: '#addon-price-list',
            loaderElementClass: '.loader'
        },

        /**
         * Returns auto complete url for fetching product addons.
         * @returns {string}
         */
        getProductAddonAutoCompletionUrl = function() {
            var allowedTypes = [
                ProductTypeManager.types.PRODUCT,
                ProductTypeManager.types.PRODUCT_WITH_VARIANTS,
                ProductTypeManager.types.PRODUCT_ADDON
            ];

            return '/admin/api/products?flat=true&searchFields=number,name&fields=id,name,number&type=' +
                allowedTypes.join(',')
        },

        /**
         * Bind custom events.
         */
        bindCustomEvents = function() {
            // Listen for status changes, enable the save button.
            this.sandbox.on('product.state.change', handleStatusChanges.bind(this));

            // Save status if the save button in the toolbar is clicked.
            this.sandbox.on('sulu.toolbar.save', saveProduct.bind(this));

            // Listen on the saved event for changes in the status of the product.
            this.sandbox.on('sulu.products.saved', handleProductSaved.bind(this));

            // Enable toolbar items if elements are selected.
            this.sandbox.on(
                'husky.datagrid.' + constants.datagridInstanceName + '.number.selections',
                enableToolbarItems.bind(this)
            );

            // Start prices component if a product is selected in the addon auto-complete search.
            this.sandbox.on('husky.auto-complete.addons-search.select', function(selectedAddon) {
                handleAutoCompleteSelection.call(this, selectedAddon);
            }, this);
        },

        /**
         * Bind dom events.
         */
        bindDomEvents = function() {
            // Disable price field if checkbox is not checked.
            this.sandbox.dom.on(this.$el, 'change', function(event) {
                handlePriceCheckboxSelection.call(this, event);
            }.bind(this), '.change-price');
        },

        /**
         * Handle status change.
         *
         * @param {object} status
         */
        handleStatusChanges = function(status) {
            if (!this.options.data || !this.options.data.attributes.status ||
                this.options.data.attributes.status.id !== status.id
            ) {
                this.status = status;
                this.options.data.attributes.status = this.status;
                setHeaderBar.call(this, false);
            }
        },

        /**
         * Handle product saved.
         */
        handleProductSaved = function() {
            setHeaderBar.call(this, true);
            this.options.data.attributes.status = this.status;
        },

        /**
         * Enable toolbar items.
         *
         * @param {number} number
         */
        enableToolbarItems = function(number) {
            var action = number > 0 ? 'enable' : 'disable';
            this.sandbox.emit(
                'husky.toolbar.' + constants.toolbarInstanceName + '.item.' + action,
                'deleteSelected',
                false)
        },

        /**
         * Handle auto complete selection.
         *
         * @param {object} selectedAddon
         */
        handleAutoCompleteSelection = function(selectedAddon) {
            // Remove prices (only important if another product was already selected before).
            this.sandbox.dom.empty(this.$find(constants.priceListElementId));

            // Start the price list loader.
            startLoader.call(this, constants.priceListElementId);

            var selectedAddon = $.getJSON('api/products/' + selectedAddon.id + '?locale=' + this.options.locale);
            selectedAddon.done(function(data) {
                startOverlayPricesComponent.call(this, data, null);
            }.bind(this));
        },

        /**
         * Handle price checkbox selection.
         *
         * @param {object} event
         */
        handlePriceCheckboxSelection = function(event) {
            var $target = $(event.currentTarget);
            var checked = $target.is(':checked');
            var $priceField = this.sandbox.dom.find('#addon-price-' + $target.data('currency'));

            $priceField.prop('disabled', !checked);
        },

        /**
         * Starts a loader and adds it to the dom.
         *
         * @param string elementId
         */
        startLoader = function(elementId) {
            var $container = this.sandbox.dom.createElement('<div class="' + constants.loaderClass + '"/>');
            var $element = this.sandbox.dom.find(elementId);
            this.sandbox.dom.append($element, $container);
            this.sandbox.start([
                {
                    name: 'loader@husky',
                    options: {
                        el: $container,
                        size: '60px',
                        color: '#ccc'
                    }
                }
            ]);
        },

        /**
         * Removes the loader.
         */
        removeLoader = function() {
            this.sandbox.dom.remove('.' + constants.loaderClass);
        },

        /**
         * @param {Boolean} saved Defines if saved state should be shown.
         */
        setHeaderBar = function(saved) {
            if (saved !== this.productSaved) {
                if (!!saved) {
                    this.sandbox.emit('sulu.header.toolbar.item.disable', 'save', true);
                } else {
                    this.sandbox.emit('sulu.header.toolbar.item.enable', 'save', false);
                }
            }
            this.productSaved = saved;
        },

        /**
         * Retrieve currencies.
         */
        retrieveCurrencies = function() {
            if (!this.currenciesRequest) {
                var currenciesUrl = 'api/currencies?flat=true&locale=' + this.options.locale;
                this.currenciesRequest = $.getJSON(currenciesUrl, function(data) {
                    currencies = data._embedded.currencies;
                }.bind(this));
            }

            return this.currenciesRequest;
        },

        /**
         * Saves the product, only needed for a status change.
         */
        saveProduct = function() {
            this.options.data.attributes.status = this.status;
            this.sandbox.emit('sulu.products.save', this.options.data.attributes, true);
            this.saved = false;
        },

        /**
         * Called when OK on overlay was clicked, saves the product addon.
         */
        overlayOkClicked = function() {
            var productAddon;
            var httpType = 'post';

            // Exit if no addon is selected in overlay.
            if (currentSelectedAddon === null) {
                return;
            }

            if (currentProductAddon !== null) {
                productAddon = ProductAddon.findOrCreate({id: currentProductAddon.id});
                httpType = 'put';
            } else {
                productAddon = new ProductAddon();
            }

            productAddon.set({addon: currentSelectedAddon.id});

            var prices = [];
            retrieveCurrencies.call(this).done(function() {
                this.sandbox.util.foreach(currencies, function(currency) {
                    var $overwrittenCheckbox = this.sandbox.dom.find('#change-price-' + currency.code, this.$el);

                    if (!!$overwrittenCheckbox[0] && $overwrittenCheckbox[0].checked) {
                        var price = {};
                        price.currency = currency.code;

                        var priceValue = this.sandbox.parseFloat(this.sandbox.dom.val('#addon-price-' + currency.code));

                        if (!isNaN(priceValue)) {
                            price.value = priceValue;
                            prices.push(price);
                        }
                    }
                }.bind(this));
            }.bind(this));

            productAddon.set({prices: prices});

            productAddon.saveToProduct(this.options.data.id, {
                type: httpType,
                success: function() {
                    this.sandbox.emit('husky.datagrid.' + constants.datagridInstanceName + '.update');
                    this.sandbox.emit('sulu.header.toolbar.item.disable', 'save', true);
                }.bind(this),
                error: function() {
                    this.sandbox.emit('sulu.labels.error.show', 'product.product-addons.save-error');
                }.bind(this)
            });
        },

        /**
         * Delete selected product addons.
         */
        removeSelected = function() {
            this.sandbox.emit('husky.datagrid.' + constants.datagridInstanceName + '.items.get-selected',
                function(ids) {
                    removeAddons.call(this, ids);
                }.bind(this));
        },

        /**
         * Delete product addons.
         *
         * @param {array} ids
         */
        removeAddons = function(ids) {
            this.sandbox.util.foreach(ids, function(id) {
                var ajaxRequest = $.ajax('api/addons/' + id, {
                    method: 'delete'
                });

                ajaxRequest.done(function() {
                    this.sandbox.emit('husky.datagrid.' + constants.datagridInstanceName + '.record.remove', id);
                    this.sandbox.emit('sulu.header.toolbar.item.disable', 'save', true);
                }.bind(this));

                ajaxRequest.fail(function() {
                    this.sandbox.emit('sulu.labels.error.show', 'product.product-addons.remove-error');
                }.bind(this));
            }.bind(this));
        },

        /**
         * Starts the component for addon prices.
         *
         * @param {object} selectedAddon Product that was selected to be added as add on in the auto-complete search.
         * @param {object} productAddon Already existent product addon entity (only for edit).
         */
        startOverlayPricesComponent = function(selectedAddon, productAddon) {
            currentSelectedAddon = selectedAddon;
            currentProductAddon = productAddon;

            retrieveCurrencies.call(this).done(function() {
                var priceRows = [];
                var priceRow = null;
                var defaultPrices = {};
                var productAddonPrices = {};
                var $pricesEl;

                this.sandbox.util.foreach(selectedAddon.prices, function(defaultPrice) {
                    defaultPrices[defaultPrice.currency.code] = defaultPrice.price;
                }.bind(this));

                if (productAddon !== null) {
                    this.sandbox.util.foreach(productAddon.prices, function(productAddonPrice) {
                        productAddonPrices[productAddonPrice.currency.code] = productAddonPrice.price;
                    }.bind(this));
                }

                this.sandbox.util.foreach(currencies, function(currency) {
                    priceRow = {};
                    priceRow.id = currency.id;

                    priceRow.defaultPrice = this.sandbox.numberFormat(0, 'n');
                    if (!!defaultPrices[currency.code]) {
                        priceRow.defaultPrice = this.sandbox.numberFormat(defaultPrices[currency.code], 'n');
                    }

                    priceRow.price = priceRow.defaultPrice;
                    if (!!productAddonPrices[currency.code]) {
                        priceRow.price = this.sandbox.numberFormat(productAddonPrices[currency.code], 'n');
                    }

                    priceRow.currencyCode = currency.code;
                    priceRow.overwritten = (priceRow.defaultPrice == priceRow.price ? false : true);

                    priceRows.push(priceRow);
                    priceRow = null;
                }.bind(this));

                $pricesEl = this.$find(constants.priceListElementId);

                // Remove loader before adding the prices to the dom.
                removeLoader.call(this);

                // Add price rows.
                this.sandbox.util.foreach(priceRows, function(priceRow) {
                    priceRow.translate = this.sandbox.translate;
                    var $el = this.sandbox.util.template(PriceTemplate, priceRow);
                    this.sandbox.dom.append($pricesEl, $el);
                }.bind(this));
            }.bind(this));
        },

        /**
         * Starts the auto-complete component which is shown in the add/edit overlay.
         *
         * @param {object} productAddon
         */
        startOverlayAutoCompleteComponent = function(productAddon) {
            var autoCompleteOptions = Config.get('suluproduct.components.autocomplete.default');
            autoCompleteOptions.instanceName = 'addons-search';
            autoCompleteOptions.el = '#addons-search-field';
            autoCompleteOptions.remoteUrl = getProductAddonAutoCompletionUrl();
            autoCompleteOptions.noNewValues = true;
            autoCompleteOptions.fields = [
                {
                    id: 'number',
                    width: '60px'
                },
                {
                    id: 'name'
                }
            ];

            if (null !== productAddon) {
                autoCompleteOptions.value = productAddon.addon;
            }

            this.sandbox.start([
                {
                    name: 'auto-complete@husky',
                    options: autoCompleteOptions
                }
            ]);
        },

        /**
         * Show edit/new overlay.
         *
         * @param {object} productAddon
         */
        showOverlay = function(title) {
            // Create a container for the overlay.
            var $overlayContent = this.sandbox.dom.createElement(this.sandbox.util.template(OverlayTemplate, {
                translate: this.sandbox.translate
            }));

            var $overlay = this.sandbox.dom.createElement('<div>');
            this.sandbox.dom.append(this.$el, $overlay);

            // Start overlay component.
            this.sandbox.start([
                {
                    name: 'overlay@husky',
                    options: {
                        el: $overlay,
                        supportKeyInput: false,
                        title: this.sandbox.translate(title),
                        skin: 'wide',
                        openOnStart: true,
                        removeOnClose: true,
                        instanceName: constants.overlayInstanceName,
                        data: $overlayContent,
                        okCallback: overlayOkClicked.bind(this)
                    }
                }
            ]);
        },

        /**
         * Show add overlay.
         */
        showAddOverlay = function() {
            showOverlay.call(this, 'product.product-addons.add');

            // Start auto-complete component.
            startOverlayAutoCompleteComponent.call(this, null);
        },

        /**
         * Show edit overlay.
         *
         * @param {number} id
         */
        showEditOverlay = function(id) {
            showOverlay.call(this, 'product.product-addons.edit');

            // Show a loader until the prices are loaded.
            this.sandbox.once('husky.overlay.product-addon-overlay.opened', function() {
                startLoader.call(this, constants.priceListElementId);
            }.bind(this));

            var ajaxRequest = $.getJSON('api/addons/' + id + '?locale=' + this.options.locale);
            ajaxRequest.done(function(productAddon) {
                // Start auto-complete component.
                startOverlayAutoCompleteComponent.call(this, productAddon);

                // Wait until search is initialized to disable input.
                this.sandbox.once('husky.auto-complete.addons-search.initialized', function() {
                    this.sandbox.dom.attr(this.$find('#addons-search'), 'disabled', 'disabled');
                }.bind(this));

                // Show prices.
                startOverlayPricesComponent.call(this, productAddon.addon, productAddon);
            }.bind(this));
        },

        /**
         * Calls toolbar and list components.
         */
        startListComponents = function() {
            this.sandbox.sulu.initListToolbarAndList.call(
                this,
                'addons',
                'api/addon/fields',
                {
                    el: '#product-addons-toolbar',
                    instanceName: constants.toolbarInstanceName,
                    hasSearch: false,
                    template: this.sandbox.sulu.buttons.get({
                        add: {
                            options: {
                                callback: showAddOverlay.bind(this)
                            }
                        },
                        deleteSelected: {
                            options: {
                                callback: removeSelected.bind(this)
                            }
                        }
                    })
                },
                {
                    el: '#product-addons-list',
                    url: 'api/products/' + this.options.data.id + '/addons?flat=true',
                    instanceName: constants.datagridInstanceName,
                    resultKey: 'addons',
                    actionCallback: showEditOverlay.bind(this),
                    viewOptions: {
                        table: {
                            selectItem: {
                                type: 'checkbox'
                            }
                        }
                    }
                }
            );
        },

        /**
         * Initializes the addons list.
         */
        initList = function() {
            this.sandbox.start('#product-addons-form');
            startListComponents.call(this);
        };

    return {
        name: 'Sulu Product Addons View',

        templates: ['/admin/product/template/product/addons'],

        render: function() {
            this.sandbox.dom.html(this.$el, this.renderTemplate(this.templates[0]));
            initList.call(this);
        },

        /**
         * Components constructor function.
         */
        initialize: function() {
            bindCustomEvents.call(this);
            bindDomEvents.call(this);

            this.status = this.options.data.attributes.status;

            // Reset status if it has been changed before and has not been saved.
            this.sandbox.emit('product.state.change', this.status);
            this.render();
            this.sandbox.emit('sulu.header.toolbar.item.disable', 'save', false);
        }
    };
});
