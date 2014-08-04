/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define(['suluproduct/models/product', 'app-config'], function(Product, AppConfig) {

    'use strict';

    return {

        initialize: function() {
            this.product = null;

            this.bindCustomEvents();
            if (this.options.display === 'list') {
                this.renderList();
            } else if (this.options.display === 'form') {
                this.renderForm();
            } else if (this.options.display === 'import') {
                this.renderImport();
            }
        },

        bindCustomEvents: function() {
            this.sandbox.on('sulu.products.new', function() {
                this.sandbox.emit('sulu.router.navigate', 'pim/products/add');
            }.bind(this));

            this.sandbox.on('sulu.products.save', function(data) {
                this.save(data);
            }.bind(this));

            this.sandbox.on('sulu.products.import', function() {
                this.sandbox.emit('sulu.router.navigate', 'pim/products/import');
            }.bind(this));

            this.sandbox.on('husky.datagrid.item.click', function(id) {
                this.load(id, AppConfig.getUser().locale);
            }.bind(this));

            this.sandbox.on('sulu.products.list', function() {
                this.sandbox.emit('sulu.router.navigate', 'pim/products');
            }.bind(this));

            this.sandbox.on('sulu.header.language-changed', function(locale) {
                this.load(this.options.id, locale);
            }, this);
        },

        save: function(data) {
            this.sandbox.emit('sulu.header.toolbar.item.loading', 'save-button');
            this.product.set(data);
            this.product.save(null, {
                success: function(response) {
                    var model = response.toJSON();
                    if (!!data.id) {
                        this.sandbox.emit('sulu.products.saved', model);
                    } else {
                        this.sandbox.emit('sulu.router.navigate', 'pim/products/edit:' + model.id + '/details');
                    }
                }.bind(this),
                error: function() {
                    this.sandbox.logger.log('error while saving product');
                }.bind(this)
            });
        },

        load: function(id, localization) {
            this.sandbox.emit('sulu.router.navigate', 'pim/products/' + localization + '/edit:' + id + '/details');
        },

        renderForm: function() {
            this.product = new Product();

            var $form = this.sandbox.dom.createElement('<div id="products-form-container"/>'),
                component = {
                    name: 'products/components/form@suluproduct',
                    options: {
                        el: $form,
                        data: this.product.defaults()
                    }
                };

            this.html($form);

            if (!!this.options.id) {
                this.product.set({id: this.options.id});
                this.product.fetchLocale(this.options.locale, {
                    success: function(model) {
                        component.options.data = model.toJSON();
                        this.sandbox.start([component]);
                    }.bind(this)
                });
            } else {
                this.sandbox.start([component]);
            }
        },

        /**
         * Creates the view for the flat product list
         */
        renderList: function() {
            var $list = this.sandbox.dom.createElement('<div id="products-list-container"/>');
            this.html($list);
            this.sandbox.start([
                {name: 'products/components/list@suluproduct', options: { el: $list}}
            ]);
        },

        /**
         * Creates the view for the product import
         */
        renderImport: function() {
            var $container = this.sandbox.dom.createElement('<div id="products-import"/>');
            this.html($container);
            this.sandbox.start([
                {name: 'products/components/import@suluproduct', options: { el: $container}}
            ]);
        }
    };
});
