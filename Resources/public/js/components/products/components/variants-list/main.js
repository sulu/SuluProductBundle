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
    'suluproduct/util/header',
    'suluproduct/util/productUpdate'
], function (Config, HeaderUtil, ProductUpdate) {
    'use strict';

    var constants = {
            productOverlayName: 'variants',
            toolbarInstanceName: 'variants'
        },

        maxLengthTitle = 60,

        renderList = function () {
            var statusTitle, statusIcon;

            if (this.status.id === Config.get('product.status.active').id) {
                statusTitle = this.sandbox.translate(Config.get('product.status.active').key);
                statusIcon = 'husky-publish';
            } else {
                statusTitle = this.sandbox.translate(Config.get('product.status.inactive').key);
                statusIcon = 'husky-test';
            }

            this.sandbox.sulu.initListToolbarAndList.call(
                this,
                'product-variants-list',
                '/admin/api/products/fields',
                {
                    el: '#list-toolbar',
                    inHeader: true,

                    instanceName: constants.toolbarInstanceName,
                    parentTemplate: 'default',
                    template: function () {
                        return [
                            {
                                id: 'save-button',
                                icon: 'floppy-o',
                                iconSize: 'large',
                                class: 'highlight',
                                position:11,
                                group: 'left',
                                disabled: true,
                                callback: function() {
                                    this.sandbox.emit('sulu.header.toolbar.save');
                                }.bind(this)
                            },
                            {
                                id: 'workflow',
                                icon: statusIcon,
                                title: statusTitle,
                                type: 'select',
                                group: 'left',
                                position: 30,
                                items: [
                                    {
                                        id: 'inactive',
                                        icon: 'husky-test',
                                        title: this.sandbox.translate('product.workfow.set.inactive'),
                                        callback: function() {
                                            changeState.call(this, Config.get('product.status.inactive').id);
                                        }.bind(this)
                                    },
                                    {
                                        id: 'active',
                                        icon: 'husky-publish',
                                        title: this.sandbox.translate('product.workfow.set.active'),
                                        callback: function() {
                                            changeState.call(this, Config.get('product.status.active').id);
                                        }.bind(this)
                                    }
                                ]
                            }
                        ];
                    }.bind(this)
                },
                {
                    el: '#product-variants',
                    resultKey: 'products',
                    url: '/admin/api/products/' + this.options.data.id + '/variants?flat=true'
                }
            );
        },

        changeState = function(id) {
            if (!this.options.data.status || this.options.data.status.id !== id) {
                this.status = {id: id};
                setHeaderBar.call(this, false);
            }
        },

        save = function() {
            this.options.data.status = this.status;
            this.sandbox.emit('sulu.products.save', this.options.data);
        },

        setHeaderBar = function(saved) {
            if (saved !== this.saved) {
                this.sandbox.emit('husky.toolbar.'+constants.toolbarInstanceName+'.item.enable','save-button',true);
            }
            this.saved = saved;
            propagateState.call(this);
        },

        /**
         * Propagates the state of the content with an event
         *  sulu.content.saved when the content has been saved
         *  sulu.content.changed when the content has been changed
         */
        propagateState = function() {
            if (!!this.saved) {
                this.sandbox.emit('sulu.content.saved');
            } else {
                this.sandbox.emit('sulu.content.changed');
            }
        },

        bindCustomEvents = function() {
            // resets the toolbar for other tabs
            this.sandbox.on('husky.tabs.header.item.select', function(){
                HeaderUtil.resetToolbar(this.sandbox, this.options.locale, this.status);
            },this);

            this.sandbox.on('sulu.header.toolbar.item.loading', function(button) {
                this.sandbox.emit('husky.toolbar.'+constants.toolbarInstanceName+'.item.loading', button, true);
            }, this);

            this.sandbox.on('sulu.products.saved', function(model) {
                this.options.data = model;
                this.status = model.status;
                this.saved = true;
                this.sandbox.emit('husky.toolbar.'+constants.toolbarInstanceName+'.item.disable', 'save-button', false);
            }, this);

            this.sandbox.on('sulu.header.toolbar.save', function(){
                save.call(this);
            }, this);

            this.sandbox.on('sulu.list-toolbar.add', function () {
                startAddOverlay.call(this);
            }, this);

            this.sandbox.on('sulu.list-toolbar.delete', function () {
                this.sandbox.emit('husky.datagrid.items.get-selected', function (ids) {
                    this.sandbox.emit('sulu.products.variants.delete', ids);
                }.bind(this));
            }, this);

            this.sandbox.on('sulu.products.variant.deleted', function (id) {
                this.sandbox.emit('husky.datagrid.record.remove', id);
            }, this);
        },

        render = function () {
            this.sandbox.dom.html(this.$el, this.renderTemplate('/admin/product/template/product/variants'));

            renderList.call(this);

            initializeAddOverlay.call(this);
        },

        setHeaderInformation = function () {
            var title = 'pim.product.title',
                breadcrumb = [
                    {title: 'navigation.pim'},
                    {title: 'pim.products.title'}
                ];
            if (!!this.options.data && !!this.options.data.name) {
                title = this.options.data.name;
            }
            title = this.sandbox.util.cropTail(title, maxLengthTitle);
            this.sandbox.emit('sulu.header.set-title', title);

            if (!!this.options.data && !!this.options.data.number) {
                breadcrumb.push({
                    title: '#' + this.options.data.number
                });
            } else {
                breadcrumb.push({
                    title: 'pim.product.title'
                });
            }
            this.sandbox.emit('sulu.header.set-breadcrumb', breadcrumb);
        },

        initializeAddOverlay = function () {
            var $el = this.sandbox.dom.createElement('<div/>');
            this.sandbox.dom.append(this.$el, $el);

            this.sandbox.start([
                {
                    name: 'products-overlay@suluproduct',
                    options: {
                        el: $el,
                        instanceName: constants.productOverlayName,
                        slides: [
                            {
                                title: 'Products'
                            }
                        ],
                        translations: {
                            addProducts: 'products-overlay.add-variant'
                        },
                        filter: {
                            parent: null,
                            types: [1, 4] // TODO use better variables for types
                        }
                    }
                }
            ]);
        },

        startAddOverlay = function () {
            this.sandbox.emit('sulu.products.products-overlay.' + constants.productOverlayName + '.open');
        };

    return {
        name: 'Sulu Product Variants List',

        view: true,

        templates: ['/admin/product/template/product/variants'],

        initialize: function() {
            this.sandbox.data.when(ProductUpdate.update(this.sandbox)).then(function(data) {
                this.options.data = data;
                this.saved = true;
                this.status = !!this.options.data ? this.options.data.status : Config.get('product.status.active');

                render.call(this);
                bindCustomEvents.call(this);
                setHeaderInformation.call(this);
            }.bind(this));
        }
    };
});
