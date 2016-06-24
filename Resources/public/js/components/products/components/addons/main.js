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
    'text!suluproduct/components/products/components/addons/overlay-content.html'
], function(Config, OverlayTpl) {
    'use strict';

    var constants = {
        datagridInstanceName: 'product-addon-datagrid',
        toolbarInstanceName: 'product-addon-oolbar',
        overlayInstanceName: 'product-addon-overlay',
        selectInstanceName: 'product-addon-select'
    };

    // TODO check if needed, maybe move to constants
    var addonId = null,
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
         * TODO check
         * Create overlay content for add addon overlay
         */
        createAddOverlayContent = function() {
            addonId = null;

            // create container for overlay
            var $overlayContent = this.sandbox.dom.createElement(this.sandbox.util.template(OverlayTpl, {
                translate: this.sandbox.translate
            }));
            this.sandbox.dom.append(this.$el, $overlayContent);

            return $overlayContent;
        },

        /**
         * TODO check
         * Create the overlay
         */
        showEditOverlay = function() {
            // call JSON to get the addons from the server then create the overlay after it's done
            var availableAddonsUrl = 'api/products?flat=true&type=3&locale=' + this.options.locale;
            var ajaxRequest = $.getJSON(availableAddonsUrl, function(data) {
                this.availableAddons = [];

                $.each(data._embedded.products, function(key, value) {
                    this.availableAddons.push({
                        id: value.id,
                        name: value.name
                    });
                }.bind(this));
            }.bind(this));

            ajaxRequest.done(function() {
                // create container for overlay
                var $overlay = this.sandbox.dom.createElement('<div>');
                this.sandbox.dom.append(this.$el, $overlay);

                // create content
                this.sandbox.start([
                    {
                        name: 'overlay@husky',
                        options: {
                            el: $overlay,
                            supportKeyInput: false,
                            title: this.sandbox.translate('product.addon.overlay.title'),
                            skin: 'normal',
                            openOnStart: true,
                            removeOnClose: true,
                            instanceName: constants.overlayInstanceName,
                            data: createAddOverlayContent.call(this),
                            okCallback: overlayOkClicked.bind(this)
                        }
                    }
                ]);

            }.bind(this));

            ajaxRequest.fail(function() {
                console.log('Error retrieving addons from server');
            }.bind(this));

            ajaxRequest.complete(function() {
                // create dropbox in overlay
                var selectOptions = {
                    el: '#selectBox',
                    instanceName: constants.selectInstanceName,
                    multipleSelect: false,
                    defaultLabel: this.sandbox.translate('product.addon.overlay.defaultlabel'),
                    valueName: 'name',
                    isNative: true,
                    data: this.availableAddons
                };

                this.sandbox.start([
                    // start select
                    {
                        name: 'select@husky',
                        options: selectOptions
                    }
                ]);

                // define select event for dropbox
                this.sandbox.on('husky.select.' + constants.selectInstanceName + '.selected.item', function(item) {
                    addonId = parseInt(item);
                });
            }.bind(this));
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
         * TODO get currencies from DB:
         * - Implement a currencies API
         * - Add to JsConfig
         */
         getCurrencies = function() {
            return [
                {
                    id: 1,
                    code: 'EUR'
                },
                {
                    id: 2,
                    code: 'CHF'
                }
            ];
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
                                callback: showEditOverlay.bind(this)
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
