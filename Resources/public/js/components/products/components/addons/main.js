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
    'text!suluproduct/components/products/components/addons/overlay.html'
], function(Config, OverlayTpl) {
    'use strict';

    var currencies = null,

        constants = {
            datagridInstanceName: 'product-addon-datagrid',
            toolbarInstanceName: 'product-addon-oolbar',
            overlayInstanceName: 'product-addon-overlay',
            selectInstanceName: 'product-addon-select'
        },

        // TODO check if needed, maybe move to constants
        addonId = null,
        actions = {
            ADD: 1,
            DELETE: 2,
            UPDATE: 3
        },

        /**
         * TODO check
         * bind custom events
         */
        bindCustomEvents = function() {
            this.sandbox.on('sulu.toolbar.delete', function() {
                this.sandbox.emit('sulu.product.delete', this.options.data.id);
            }.bind(this));

            this.sandbox.on('product.state.change', function(status) {
                if (!this.options.data ||
                    !this.options.data.attributes.status ||
                    this.options.data.attributes.status.id !== status.id
                ) {
                    this.status = status;
                    this.options.data.attributes.status = this.status;
                    setHeaderBar.call(this, false);
                }
            }, this);

            this.sandbox.on('sulu.toolbar.save', function() {
                this.sendData = {};
                this.sendData.status = this.status;
                this.sendData.id = this.options.data.id;
                save.call(this);
            }, this);

            this.sandbox.on('sulu.products.saved', function(data) {

                var addons = data.addons;

                // Select action
                if (data.action === actions.ADD) {
                    // ADD RECORD IN DATAGRID
                    var addon = _.findWhere(addons, {'addonId': data.addonIdAdded});
                    this.sandbox.emit('husky.datagrid.' + constants.datagridInstanceName + '.record.add', addon);
                } else if (data.action === actions.DELETE) {
                    // DELETE RECORDs IN DATAGRID
                    $.each(data.addonIdsDeleted, function(key, id) {
                        this.sandbox.emit('husky.datagrid.' + constants.datagridInstanceName + '.record.remove', id);
                    }.bind(this));
                } else if (data.action === actions.UPDATE) {
                    // UPDATE DATAGRID WITH RECEIVED RECORDS
                    this.sandbox.emit('husky.datagrid.' + constants.datagridInstanceName + '.records.set', addons);
                }

                setHeaderBar.call(this, true);
                //this.options.data = data;
                this.options.data.attributes.status = this.status;
            }, this);

            // enable toolbar items
            this.sandbox.on('husky.datagrid.' + constants.datagridInstanceName + '.number.selections', function(number) {
                var postfix = number > 0 ? 'enable' : 'disable';
                this.sandbox.emit(
                    'husky.toolbar.' + constants.toolbarInstanceName + '.item.' + postfix,
                    'delete',
                    false)
            }, this);

            // auto-complete search: item selected
            this.sandbox.on('husky.auto-complete.addons-search.select', function() {
                startOverlayPricesComponent.call(this, null);
            }.bind(this));
        },

        /**
         * TODO check
         * @param {Boolean} saved defines if saved state should be shown
         */
        setHeaderBar = function(saved) {
            if (saved !== this.saved) {
                if (!!saved) {
                    this.sandbox.emit('sulu.header.toolbar.item.disable', 'save', true);
                } else {
                    this.sandbox.emit('sulu.header.toolbar.item.enable', 'save', false);
                }
            }
            this.saved = saved;
        },

        /**
         * Create overlay content for addon overlay.
         */
        createOverlayContent = function() {
            addonId = null;

            // create container for overlay
            var $overlayContent = this.sandbox.dom.createElement(this.sandbox.util.template(OverlayTpl, {
                translate: this.sandbox.translate
            }));
            this.sandbox.dom.append(this.$el, $overlayContent);

            return $overlayContent;
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
         * TODO check
         * save product addons
         */
        save = function() {
            this.saved = false;
            this.sandbox.emit('sulu.products.save', this.sendData);
        },

        /**
         * TODO check
         * called when OK on overlay was clicked
         */
        overlayOkClicked = function() {
            // exit if no addon is selected in overlay
            if (!addonId) {
                return;
            }

            this.sendData = {};
            var addonValueName = this.sandbox.dom.val('#addon-name');

            var addons = this.options.data.attributes.addons;

            var result = _.findWhere(addons, {'addonId': addonId});

            if (result) {
                result.addonValueName = addonValueName;
                result.addonValueLocale = this.options.locale;
                this.sendData.action = actions.UPDATE;
            } else {
                var newAddon = {
                    'addonId': addonId,
                    'addonValueName': addonValueName,
                    'addonValueLocale': this.options.locale
                };
                addons.push(newAddon);
                this.sendData.action = actions.ADD;
            }

            this.sendData.addonIdAdded = addonId;
            this.sendData.addons = addons;
            this.sendData.status = this.status;
            this.sendData.id = this.options.data.addons.id;

            save.call(this);
        },

        /**
         * TODO check
         * delete action function from toolbar
         */
        removeSelected = function() {
            this.sandbox.emit('husky.datagrid.' + constants.datagridInstanceName + '.items.get-selected', function(ids) {

                var addons = this.options.data.attributes.addons;
                this.sendData = {};
                var addonIdsDeleted = [];

                _.each(ids, function(value, key, list) {
                    var result = _.findWhere(addons, {'addonId': value});
                    addons = _.without(addons, result);
                    addonIdsDeleted.push(value);
                });

                this.sendData.addonIdsDeleted = addonIdsDeleted;
                this.sendData.addons = addons;
                this.sendData.status = this.status;
                this.sendData.id = this.options.data.id;
                this.sendData.action = actions.DELETE;

                save.call(this);
            }.bind(this));
        },

        /**
         * Starts the component for addon prices.
         *
         * @param {object} selectedAddon
         */
        startOverlayPricesComponent = function(selectedAddon) {
            var datagridOptions;
            var data = [];
            var id = 1;
            this.sandbox.util.foreach(currencies, function(currency) {
                data.push(
                    {
                        id: id,
                        price: 0,
                        currency: currency.code,
                        overwritten: false
                    }
                );
                id++;
            }.bind(this));

            datagridOptions = {
                el: '#addon-price-list',
                instanceName: 'product-addon-prices-datagrid',
                idKey: 'id',
                matchings: [
                    {
                        name: 'price',
                        content: 'product.addon.overlay.price',
                        type: 'number',
                        editable: true,
                    },
                    {
                        name: 'currency',
                        content: 'product.addon.overlay.currency',
                        type: 'currency'
                    },
                    {
                        name: 'overwritten',
                        content: 'product.addon.overlay.overwritten',
                        type: 'checkbox'
                    }
                ],
                viewOptions: {
                    table: {
                        editable: true,
                        selectItem: {
                            type: null
                        }
                    }
                },
                data: data
            };

            this.sandbox.start([
                {
                    name: 'datagrid@husky',
                    options: datagridOptions
                }
            ]);
        },

        /**
         * Starts the auto-complete component which is shown in the add/edit overlay.
         *
         * @param {object} selectedAddon
         */
        startOverlayAutoCompleteComponent = function(selectedAddon) {
            var autoCompleteOptions;

            autoCompleteOptions = Config.get('suluproduct.components.autocomplete.default');
            autoCompleteOptions.instanceName = 'addons-search';
            autoCompleteOptions.el = '#addons-search-field';
            autoCompleteOptions.remoteUrl = '/admin/api/products?flat=true&searchFields=number,name&fields=id,name,number&type=3';
            autoCompleteOptions.noNewValues = true;

            if (null !== selectedAddon) {
                autoCompleteOptions.value = selectedAddon.addon;
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
         * @param {object} selectedAddon
         */
        showOverlay = function(selectedAddon) {
            var $overlayContent = createOverlayContent.call(this);

            // Start auto-complete component.
            startOverlayAutoCompleteComponent.call(this, selectedAddon);

            // Load currencies.
            retrieveCurrencies.call(this).done(function() {
                // Start overlay.
                var $overlay = this.sandbox.dom.createElement('<div>');
                this.sandbox.dom.append(this.$el, $overlay);

                this.sandbox.start([
                    {
                        name: 'overlay@husky',
                        options: {
                            el: $overlay,
                            supportKeyInput: false,
                            title: this.sandbox.translate('product.addon.overlay.title'),
                            skin: 'wide',
                            openOnStart: true,
                            removeOnClose: true,
                            instanceName: constants.overlayInstanceName,
                            data: $overlayContent,
                            okCallback: overlayOkClicked.bind(this)
                        }
                    }
                ]);

                // Edit: disable addon search, show prices immediately.
                if (selectedAddon !== null) {
                    // Wait until search is initialized to disable input.
                    this.sandbox.once('husky.auto-complete.addons-search.initialized', function() {
                        this.sandbox.dom.attr(this.$find('#addons-search'), 'disabled', 'disabled');
                    }.bind(this));

                    startOverlayPricesComponent.call(this, selectedAddon);
                }
            }.bind(this));
        },

        /**
         * Show add overlay.
         */
        showAddOverlay = function() {
            showOverlay.call(this, null);
        },

        /**
         * Show edit overlay.
         *
         * @param {number} id
         */
        showEditOverlay = function(id) {
            // TODO load selected product addon with prices
            var addon = {
                id: 1,
                addon: {
                    id: 4,
                    name: 'Addon 1',
                    type: {
                        id: 3,
                        name: 'Product Erweiterung'
                    }
                },
                prices: [
                    {
                        id: 1,
                        currency: {
                            name: 'Euro',
                            id: 2,
                            code: 'EUR',
                            number: 978
                        },
                        price: 20
                    },
                    {
                        id: 1,
                        currency: {
                            name: 'Schweizer Franken',
                            id: 1,
                            code: 'CHF',
                            number: 978
                        },
                        price: 30
                    }
                ]
            };

            showOverlay.call(this, addon);
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

        initialize: function() {
            bindCustomEvents.call(this);

            if (!!this.options.data) {
                this.status = this.options.data.attributes.status;
            } else {
                this.status = Config.get('product.status.inactive');
            }

            // reset status if it has been changed before and has not been saved
            this.sandbox.emit('product.state.change', this.status);
            this.render();
            setHeaderBar.call(this, true);
        }
    };
});
