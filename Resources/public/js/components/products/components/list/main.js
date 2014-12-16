/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define(['config'], function (Config) {

    'use strict';
    var TYPE_PRODUCT = 'product',
        TYPE_PRODUCT_VARIANT = 'product-with-variants',
        TYPE_PRODUCT_ADDON = 'product-addon',
        TYPE_PRODUCT_SET = 'product-set',

        constants = {
            toolbarInstanceName: 'productsToolbar'
        },

        addProduct = function (type) {
            this.sandbox.emit('sulu.products.new', type);
        },

        setProductActive = function(){
            this.sandbox.emit('husky.datagrid.items.get-selected', function(ids) {
                this.sandbox.emit(
                    'sulu.products.workflow.triggered',
                    {
                        ids:ids,
                        status: Config.get('product.status.active').id
                    }
                );
            }.bind(this));
        },

        setProductInactive = function(){
            this.sandbox.emit('husky.datagrid.items.get-selected', function(ids) {
                this.sandbox.emit(
                    'sulu.products.workflow.triggered',
                    {
                        ids:ids,
                        status: Config.get('product.status.inactive').id
                    }
                );
            }.bind(this));
        },

        bindCustomEvents = function () {
            this.sandbox.on('sulu.list-toolbar.add', function () {
                this.sandbox.emit('sulu.products.new');
            }.bind(this));

            this.sandbox.on('sulu.product.workflow.completed', function(){
                this.sandbox.emit('husky.datagrid.update');
            }, this);

            this.sandbox.on('sulu.list-toolbar.delete', function () {
                this.sandbox.emit('husky.datagrid.items.get-selected', function (ids) {
                    this.sandbox.emit('sulu.products.delete', ids);
                }.bind(this));
            }, this);

            // enable toolbar items
            this.sandbox.on('husky.datagrid.number.selections', function(number) {
                if (number > 0) {
                    this.sandbox.emit(
                            'husky.toolbar.' + constants.toolbarInstanceName + '.item.enable',
                        'workflow',
                        false
                    );
                } else {
                    this.sandbox.emit(
                            'husky.toolbar.' + constants.toolbarInstanceName + '.item.disable',
                        'workflow',
                        false
                    );
                }
            }, this);
        };

    return {
        view: true,

        layout: {
            content: {
                width: 'max',
                leftSpace: false,
                rightSpace: false
            }
        },

        header: function () {
            return {
                title: 'pim.products.title',
                noBack: true,

                breadcrumb: [
                    {title: 'navigation.pim'},
                    {title: 'pim.products.title'}
                ]
            };
        },

        templates: ['/admin/product/template/product/list'],

        initialize: function () {
            this.render();
            bindCustomEvents.call(this);
        },

        renderGrid: function () {
            this.sandbox.dom.html(this.$el, this.renderTemplate('/admin/product/template/product/list'));

            // init list-toolbar and datagrid
            this.sandbox.sulu.initListToolbarAndList.call(this, 'productsFields', '/admin/api/products/fields',
                {
                    el: this.$find('#list-toolbar-container'),
                    instanceName: constants.toolbarInstanceName,
                    parentTemplate: 'default',
                    inHeader: true,
                    template: function () {
                        return [
                            {
                                id: 'add',
                                icon: 'plus-circle',
                                class: 'highlight-white',
                                position: 1,
                                title: this.sandbox.translate('sulu.list-toolbar.add'),
                                items: [
                                    {
                                        id: 'add-product',
                                        title: this.sandbox.translate('products.add-product'),
                                        callback: addProduct.bind(this, TYPE_PRODUCT)
                                    },
                                    {
                                        id: 'add-product-with-variants',
                                        title: this.sandbox.translate('products.add-product-with-variants'),
                                        callback: addProduct.bind(this, TYPE_PRODUCT_VARIANT)
                                    },
                                    {
                                        id: 'add-product-addon',
                                        title: this.sandbox.translate('products.add-product-addon'),
                                        callback: addProduct.bind(this, TYPE_PRODUCT_ADDON)
                                    },
                                    {
                                        id: 'add-product-set',
                                        title: this.sandbox.translate('products.add-product-set'),
                                        callback: addProduct.bind(this, TYPE_PRODUCT_SET)
                                    }
                                ]
                            },
                            {
                                id: 'workflow',
                                icon: 'husky-publish',
                                type: 'select',
                                position: 30,
                                disabled: true,

                                items: [
                                    {
                                        id: 'active',
                                        title: this.sandbox.translate('product.workfow.set.active'),
                                        callback: setProductActive.bind(this)
                                    },
                                    {
                                        id: 'inactive',
                                        title: this.sandbox.translate('product.workfow.set.inactive'),
                                        callback: setProductInactive.bind(this)
                                    }
                                ]
                            }
                        ];
                    }.bind(this)
                },
                {
                    el: this.sandbox.dom.find('#products-list', this.$el),
                    url: '/admin/api/products?flat=true&status_id='+ Config.get('product.list.statuses.ids'),
                    resultKey: 'products',
                    searchInstanceName: 'productsToolbar',
                    searchFields: ['name','number'],
                    viewOptions: {
                        table: {
                            fullWidth: true
                        }
                    }
                }
            );
        },

        render: function () {
            this.renderGrid();
        }
    };
});
