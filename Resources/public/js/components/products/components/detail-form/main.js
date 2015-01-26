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
    'suluproduct/util/productUpdate'
], function (Config, ProductUpdate) {

    'use strict';

    var types = {
            'product': 1,
            'product-with-variants': 2,
            'product-addon': 3,
            'product-set': 4
        },
        formSelector = '#product-form',
        maxLengthTitle = 60;

    return {

        name: 'Sulu Product Form',

        view: true,

        templates: ['/admin/product/template/product/form'],

        initialize: function() {
            this.sandbox.data.when(ProductUpdate.update(this.sandbox)).then(function(data) {
                this.options.data = data;
                this.saved = true;
                this.status = !!this.options.data ? this.options.data.status : Config.get('product.status.active');

                this.initializeValidation();
                this.bindCustomEvents();
                this.setHeaderBar(true);
                this.render();
                this.listenForChange();
            }.bind(this));
        },

        bindCustomEvents: function () {
            this.sandbox.on('product.state.change', function(id){
                if(!this.options.data || !this.options.data.status || this.options.data.status.id !== id){
                    this.status = {id: id};
                    this.setHeaderBar(false);
                }
            },this);

            this.sandbox.on('sulu.header.toolbar.save', function () {
                this.save();
            }.bind(this));

            this.sandbox.on('sulu.header.toolbar.delete', function () {
                this.sandbox.emit('sulu.products.delete', this.sandbox.dom.val('#id'));
            }.bind(this));

            this.sandbox.on('sulu.products.saved', function (data) {
                this.options.data = data;
                this.status = this.options.data.status;
                this.setHeaderBar(true);
                this.setHeaderInformation();
            }, this);

            // back to list
            this.sandbox.on('sulu.header.back', function () {
                this.sandbox.emit('sulu.products.list');
            }, this);

            this.sandbox.on('sulu.header.initialized', function () {
                this.setHeaderInformation();
            }, this);
        },

        initializeValidation: function () {
            this.sandbox.form.create(formSelector);
        },

        save: function () {
            if (this.sandbox.form.validate(formSelector)) {
                var data = this.sandbox.form.getData(formSelector);

                if (data.id === '') {
                    delete data.id;
                }

                data.status = this.status;

                if (!data.type && !!this.options.productType) {
                    data.type = {
                        id: types[this.options.productType]
                    };
                }

                this.sandbox.emit('sulu.products.save', data);
            }
        },

        render: function () {
            this.sandbox.dom.html(this.$el, this.renderTemplate('/admin/product/template/product/form'));

            this.setHeaderInformation();

            this.initForm(this.options.data);
        },

        initForm: function (data) {
            // set form data
            var formObject = this.sandbox.form.create(formSelector);
            formObject.initialized.then(function () {
                this.setFormData(data);
            }.bind(this));
        },

        setFormData: function (data) {
            this.sandbox.form.setData(formSelector, data).then(function () {
                this.sandbox.start(formSelector);
            }.bind(this)).fail(function (error) {
                this.sandbox.logger.error("An error occured when setting data!", error);
            }.bind(this));
        },

        setHeaderInformation: function () {
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

        // @var Bool saved - defines if saved state should be shown
        setHeaderBar: function (saved) {
            if (saved !== this.saved) {
                var type = (!!this.options.data && !!this.options.data.id) ? 'edit' : 'add';
                this.sandbox.emit('sulu.header.toolbar.state.change', type, saved, true);
                this.propagateState(saved);
            }
            this.saved = saved;
        },

        /**
         * Propagates the state of the content with an event
         *  sulu.content.saved when the content has been saved
         *  sulu.content.changed when the content has been changed
         */
        propagateState: function(saved) {
            if (!!saved) {
                this.sandbox.emit('sulu.content.saved');
            } else {
                this.sandbox.emit('sulu.content.changed');
            }
        },

        listenForChange: function () {
            this.sandbox.dom.on('#product-form', 'change', function () {
                this.setHeaderBar(false);
            }.bind(this), 'select');
            this.sandbox.dom.on('#product-form', 'keyup', function () {
                this.setHeaderBar(false);
            }.bind(this), 'input, textarea');
            this.sandbox.on('husky.select.status.selected.item', function () {
                this.setHeaderBar(false);
            }.bind(this));
            this.sandbox.on('husky.select.orderUnit.selected.item', function () {
                this.setHeaderBar(false);
            }.bind(this));
            this.sandbox.on('husky.select.contentUnit.selected.item', function () {
                this.setHeaderBar(false);
            }.bind(this));
        }
    };
});
