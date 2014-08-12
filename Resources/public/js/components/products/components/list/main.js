/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define(function () {

    'use strict';
    var TYPE_PRODUCT = 'product',
        TYPE_PRODUCT_VARIANT = 'product-with-variant',
        TYPE_PRODUCT_ADDON = 'product-addon',
        TYPE_PRODUCT_SET = 'product-set',

        addProduct = function (type) {
            this.sandbox.emit('sulu.products.new', type);
        },

        bindCustomEvents = function () {
            this.sandbox.on('sulu.list-toolbar.add', function () {
                this.sandbox.emit('sulu.products.new');
            }.bind(this));
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
                    instanceName: 'productsToolbar',
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
                                        id: 'add-basic',
                                        title: this.sandbox.translate('products.add-product'),
                                        callback: addProduct.bind(this, TYPE_PRODUCT)
                                    },
                                    {
                                        id: 'add-lead',
                                        title: this.sandbox.translate('products.add-product-with-variant'),
                                        callback: addProduct.bind(this, TYPE_PRODUCT_VARIANT)
                                    },
                                    {
                                        id: 'add-customer',
                                        title: this.sandbox.translate('products.add-product-addon'),
                                        callback: addProduct.bind(this, TYPE_PRODUCT_ADDON)
                                    },
                                    {
                                        id: 'add-supplier',
                                        title: this.sandbox.translate('products.add-product-set'),
                                        callback: addProduct.bind(this, TYPE_PRODUCT_SET)
                                    }
                                ]
                            }
                        ];
                    }.bind(this)
                },
                {
                    el: this.sandbox.dom.find('#products-list', this.$el),
                    url: '/admin/api/products?flat=true',
                    resultKey: 'products',
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
