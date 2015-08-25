/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define(['config'], function(Config) {

    'use strict';
    var TYPE_PRODUCT = 'product',
        TYPE_PRODUCT_VARIANT = 'product-with-variants',
        TYPE_PRODUCT_ADDON = 'product-addon',
        TYPE_PRODUCT_SET = 'product-set',

        constants = {
            toolbarInstanceName: 'productsToolbar',
            datagridInstanceName: 'products'

        },

        addProduct = function(type) {
            this.sandbox.emit('sulu.products.new', type);
        },

        setProductActive = function() {
            this.sandbox.emit('husky.datagrid.' + constants.datagridInstanceName + '.items.get-selected', function(ids) {
                this.sandbox.emit(
                    'sulu.products.workflow.triggered',
                    {
                        ids: ids,
                        status: Config.get('product.status.active').id
                    }
                );
            }.bind(this));
        },

        setProductInactive = function() {
            this.sandbox.emit('husky.datagrid.' + constants.datagridInstanceName + '.items.get-selected', function(ids) {
                this.sandbox.emit(
                    'sulu.products.workflow.triggered',
                    {
                        ids: ids,
                        status: Config.get('product.status.inactive').id
                    }
                );
            }.bind(this));
        },

        bindCustomEvents = function() {
            this.sandbox.on('sulu.toolbar.add', function() {
                this.sandbox.emit('sulu.products.new');
            }.bind(this));

            this.sandbox.on('sulu.product.workflow.completed', function() {
                this.sandbox.emit('husky.datagrid.' + constants.datagridInstanceName + '.update');
            }, this);

            this.sandbox.on('sulu.toolbar.delete', function() {
                this.sandbox.emit('husky.datagrid.' + constants.datagridInstanceName + '.items.get-selected', function(ids) {
                    this.sandbox.emit('sulu.products.delete', ids);
                }.bind(this));
            }, this);

            // checkbox clicked
            this.sandbox.on('husky.datagrid.' + constants.datagridInstanceName + '.number.selections', function(number) {
                var postfix = number > 0 ? 'enable' : 'disable';
                this.sandbox.emit('sulu.header.toolbar.item.' + postfix, 'deleteSelected', false);
            }.bind(this));

            // enable toolbar items
            this.sandbox.on('husky.datagrid.' + constants.datagridInstanceName + '.number.selections', function(number) {
                var postfix = number > 0 ? 'enable' : 'disable',
                    icon = number > 0 ? 'husky-publish' : 'husky-deactivated';

                this.sandbox.emit(
                    'husky.toolbar.' + constants.toolbarInstanceName + '.item.' + postfix,
                    'productWorkflow',
                    false
                );
                this.sandbox.emit(
                    'husky.toolbar.' + constants.toolbarInstanceName + '.button.set',
                    'productWorkflow',
                    {icon: icon}
                );
            }, this);

            this.sandbox.on('sulu.toolbar.productWorkflow.active', setProductActive.bind(this));
            this.sandbox.on('sulu.toolbar.productWorkflow.inactive', setProductInactive.bind(this));
        },

        getToolbarTemplate = function() {
            return this.sandbox.sulu.buttons.get({
                settings: {
                    options: {
                        dropdownItems: [
                            {
                                type: 'columnOptions'
                            }
                        ]
                    }
                },
                productWorkflow: {}
            });
        },

        datagridAction = function(id) {
            this.sandbox.emit('sulu.products.load', id);
        };

    return {
        view: true,

        layout: {
            content: {
                width: 'max'
            }
        },

        header: function() {
            return {
                title: 'pim.products.title',
                noBack: true,
                toolbar: {
                    buttons: {
                        add: {
                            options: {
                                dropdownItems: [
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
                            }
                        },
                        deleteSelected: {}
                    }
                }
            };
        },

        templates: ['/admin/product/template/product/list'],

        initialize: function() {
            this.render();
            bindCustomEvents.call(this);
        },

        renderGrid: function() {
            var toolbarTemplate = getToolbarTemplate.call(this),
                toolbarExtension = Config.get('product.toolbar.extension');

            this.sandbox.dom.html(this.$el, this.renderTemplate('/admin/product/template/product/list'));

            // extend default products list toolbar with some custom fields
            if (!!toolbarExtension) {
                toolbarTemplate.push.apply(toolbarTemplate, toolbarExtension);
            }

            // init list-toolbar and datagrid
            this.sandbox.sulu.initListToolbarAndList.call(this, 'productsFields', '/admin/api/products/fields',
                {
                    el: this.$find('#list-toolbar-container'),
                    instanceName: constants.toolbarInstanceName,
                    inHeader: true,
                    template: toolbarTemplate,
                    groups: [
                        {
                            id: 1,
                            align: 'left'
                        },
                        {
                            id: 2,
                            align: 'right'
                        },
                        {
                            id: 3,
                            align: 'left'
                        }
                    ]
                },
                {
                    el: this.sandbox.dom.find('#products-list', this.$el),
                    url: '/admin/api/products?flat=true&status_id=' + Config.get('product.list.statuses.ids'),
                    resultKey: 'products',
                    searchInstanceName: 'productsToolbar',
                    searchFields: ['name', 'number', 'supplier'],
                    instanceName: constants.datagridInstanceName,
                    actionCallback: datagridAction.bind(this)
                },
                'products',
                '#products-list-info'
            );
        },

        render: function() {
            this.renderGrid();
        }
    };
});
