/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define([
        'suluproduct/models/product',
        'sulucategory/model/category',
        'app-config'
    ], function (Product, Category, AppConfig) {
        'use strict';

        var types = {
                1: 'product',
                2: 'product-with-variants',
                3: 'product-addon',
                4: 'product-set'
            },
            eventNamespace = 'sulu.products.',

            /**
             * @event sulu.products.new
             * @description Opens the form for a new product
             */
            PRODUCT_NEW = eventNamespace + 'new',

            /**
             * @event sulu.products.save
             * @description Saves a given product
             */
            PRODUCT_SAVE = eventNamespace + 'save',

            /**
             * @event sulu.products.delete
             * @description Deletes the given products
             */
            PRODUCT_DELETE = eventNamespace + 'delete',

            /**
             * @description Opens the form for importing products
             */
            PRODUCT_IMPORT = eventNamespace + 'import',

            /**
             * @event sulu.products.list
             * @description Shows the list for products
             */
            PRODUCT_LIST = eventNamespace + 'list',

            /**
             * @event sulu.products.variants.delete
             * @description Deletes the given variants from the current product
             */
            PRODUCT_VARIANT_DELETE = eventNamespace + 'variants.delete';

        return {
            initialize: function () {
                this.product = null;

                this.bindCustomEvents();
                if (this.options.display === 'list') {
                    this.renderList();
                } else if (this.options.display === 'tab') {
                    this.renderTabs();
                } else if (this.options.display === 'import') {
                    this.renderImport();
                }
            },

            bindCustomEvents: function () {
                this.sandbox.on(PRODUCT_NEW, function (type) {
                    this.sandbox.emit(
                        'sulu.router.navigate',
                            'pim/products/' + AppConfig.getUser().locale + '/add/type:' + type
                    );
                }.bind(this));

                this.sandbox.on(PRODUCT_SAVE, function (data) {
                    this.save(data);
                }.bind(this));

                this.sandbox.on(PRODUCT_DELETE, function (ids) {
                    this.del(ids);
                }, this);

                this.sandbox.on(PRODUCT_IMPORT, function () {
                    this.sandbox.emit('sulu.router.navigate', 'pim/products/import');
                }.bind(this));

                this.sandbox.on('husky.datagrid.item.click', function (id) {
                    this.load(id, AppConfig.getUser().locale);
                }.bind(this));

                this.sandbox.on(PRODUCT_LIST, function () {
                    this.sandbox.emit('sulu.router.navigate', 'pim/products');
                }.bind(this));

                this.sandbox.on('sulu.header.language-changed', function (locale) {
                    this.load(this.options.id, locale);
                }, this);

                this.sandbox.on('sulu.products.products-overlay.variants.add', function (id, callback) {
                    this.addVariant(id, callback);
                }, this);

                this.sandbox.on(PRODUCT_VARIANT_DELETE, function (ids) {
                    this.deleteVariants(ids);
                }, this);
            },

            save: function (data) {
                this.sandbox.emit('sulu.header.toolbar.item.loading', 'save-button');
                this.product.set(data);

                // FIXME the categories should already be loaded correctly here
                this.product.get('categories').reset();
                this.sandbox.util.foreach(data.categories, function (id) {
                    var category = Category.findOrCreate({id: id});
                    this.product.get('categories').add(category);
                }.bind(this));

                this.product.saveLocale(this.options.locale, {
                    success: function (response) {
                        var model = response.toJSON();
                        if (!!data.id) {
                            this.sandbox.emit('sulu.products.saved', model);
                        } else {
                            this.load(model.id, this.options.locale);
                        }
                    }.bind(this),
                    error: function () {
                        this.sandbox.logger.log('error while saving product');
                    }.bind(this)
                });
            },

            load: function (id, localization) {
                this.sandbox.emit('sulu.router.navigate', 'pim/products/' + localization + '/edit:' + id + '/details');
            },

            del: function (ids) {
                this.confirmDeleteDialog(function (wasConfirmed) {
                    if (wasConfirmed) {
                        this.sandbox.util.each(ids, function (key, id) {
                            var product = new Product({id: id});
                            product.destroy({
                                success: function () {
                                    this.sandbox.emit('husky.datagrid.record.remove', id);
                                }.bind(this)
                            });
                        }.bind(this));
                    }
                }.bind(this));
            },

            addVariant: function (id) {
                this.product.get('variants').fetch(
                    {
                        data: {
                            id: id
                        },
                        type: 'POST',
                        success: function (collection, response) {
                            delete response.parent; // FIXME this is necessary because of husky datagrid
                            this.sandbox.emit('husky.datagrid.variants-datagrid.record.remove', id);
                            this.sandbox.emit('husky.datagrid.record.add', response);
                        }.bind(this)
                    }
                );
            },

            deleteVariants: function (ids) {
                this.confirmDeleteDialog(function (wasConfirmed) {
                    if (wasConfirmed) {
                        this.product.get('variants').fetch({
                            success: function (collection) {
                                this.sandbox.util.each(ids, function (key, id) {
                                    var product = collection.get(id);
                                    product.urlRoot = collection.url() + '/';

                                    product.destroy({
                                        success: function () {
                                            this.sandbox.emit('sulu.products.variant.deleted', id);
                                        }.bind(this)
                                    });
                                }.bind(this));
                            }.bind(this)
                        });
                    }
                }.bind(this));
            },

            confirmDeleteDialog: function (callbackFunction) {
                this.sandbox.emit(
                    'sulu.overlay.show-warning',
                    'sulu.overlay.be-careful',
                    'sulu.overlay.delete-desc',
                    callbackFunction.bind(this, false),
                    callbackFunction.bind(this, true)
                );
            },

            renderTabs: function () {
                this.product = new Product();

                var $tabContainer = this.sandbox.dom.createElement('<div/>'),
                    component = {
                        name: 'products/components/content@suluproduct',
                        options: {
                            el: $tabContainer,
                            locale: this.options.locale
                        }
                    };

                this.html($tabContainer);

                if (!!this.options.id) {
                    component.options.content = this.options.content;
                    component.options.id = this.options.id;
                    this.product.set({id: this.options.id});
                    this.product.fetchLocale(this.options.locale, {
                        success: function (model) {
                            component.options.data = model.toJSON();
                            component.options.productType = types[model.get('type').id];
                            this.sandbox.start([component]);
                        }.bind(this)
                    });
                } else {
                    component.options.productType = this.options.productType;
                    this.sandbox.start([component]);
                }
            },

            /**
             * Creates the view for the flat product list
             */
            renderList: function () {
                var $list = this.sandbox.dom.createElement('<div id="products-list-container"/>');
                this.html($list);
                this.sandbox.start([
                    {name: 'products/components/list@suluproduct', options: { el: $list}}
                ]);
            },

            /**
             * Creates the view for the product import
             */
            renderImport: function () {
                var $container = this.sandbox.dom.createElement('<div id="products-import"/>');
                this.html($container);
                this.sandbox.start([
                    {name: 'products/components/import@suluproduct', options: { el: $container}}
                ]);
            }
        };
    });
