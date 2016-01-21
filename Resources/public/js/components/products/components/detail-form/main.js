/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define([
    'config'
], function(Config) {

    'use strict';

    var types = {
            'product': 1,
            'product-with-variants': 2,
            'product-addon': 3,
            'product-set': 4
        },
        formSelector = '#product-form',

        constants = {
            supplierId: '#supplierField',
            autocompleteSupplierInstanceName: 'supplier'
        };

    return {

        name: 'Sulu Product Form',

        view: true,

        templates: ['/admin/product/template/product/form'],

        initialize: function() {
            this.saved = true;
            this.status = !!this.options.data ? this.options.data.attributes.status : Config.get('product.status.inactive');
            // reset status if it has been changed before and has not been saved
            this.sandbox.emit('product.state.change', this.status);

            this.initializeValidation();

            this.bindCustomEvents();

            this.setHeaderBar(true);

            this.render();

            this.listenForChange();
        },

        bindCustomEvents: function() {
            this.sandbox.on('product.state.change', function(id) {
                if (!this.options.data || !this.options.data.attributes.status || this.options.data.attributes.status.id !== id) {
                    this.status = {id: id};
                    this.setHeaderBar(false);
                }
            }, this);

            this.sandbox.on('sulu.toolbar.save', function() {
                this.save();
            }.bind(this));

            this.sandbox.on('sulu.toolbar.delete', function() {
                this.sandbox.emit('sulu.product.delete', this.sandbox.dom.val('#id'));
            }.bind(this));

            this.sandbox.on('sulu.products.saved', function() {
                this.setHeaderBar(true);
            }, this);
        },

        initializeValidation: function() {
            this.sandbox.form.create(formSelector);
        },

        save: function() {
            if (this.sandbox.form.validate(formSelector)) {
                var data = this.sandbox.form.getData(formSelector),
                    supplierId;

                if (data.id === '') {
                    delete data.id;
                }

                data.status = this.status;

                if (!data.type && !!this.options.productType) {
                    data.type = {
                        id: types[this.options.productType]
                    };
                }

                // FIXME auto complete in mapper
                // only get id, if auto-complete is not empty:
                supplierId = this.sandbox.dom.attr('#' + constants.autocompleteSupplierInstanceName, 'data-id');
                if (!!supplierId && supplierId !== 'null') {
                    data.supplier = {
                        id: supplierId
                    };
                }

                this.sandbox.emit('sulu.products.save', data);
            }
        },

        render: function() {
            this.sandbox.dom.html(this.$el, this.renderTemplate('/admin/product/template/product/form'));
            this.initSupplierAutocomplete();
            this.initForm(this.options.data);
        },

        initForm: function(data) {
            // set form data
            var formObject = this.sandbox.form.create(formSelector);
            formObject.initialized.then(function() {
                this.setFormData(data);
            }.bind(this));
        },

        setFormData: function(data) {
            if (data) {
                this.sandbox.form.setData(formSelector, data.toJSON()).then(function() {
                    this.sandbox.start(formSelector);
                }.bind(this)).fail(function(error) {
                    this.sandbox.logger.error("An error occured when setting data!", error);
                }.bind(this));
            } else {
                this.sandbox.start(formSelector);
            }
        },

        /**
         * Initializes the auto complete component with the global configuration for account-auto-completes
         */
        initSupplierAutocomplete: function() {
            var options = Config.get('sulucontact.components.autocomplete.default.account');
            options.el = constants.supplierId;
            options.value = (!!this.options.data && !!this.options.data.attributes.supplier) ? this.options.data.attributes.supplier : '';
            options.instanceName = constants.autocompleteSupplierInstanceName;
            options.remoteUrl += 'type=3';

            this.sandbox.start([
                {
                    name: 'auto-complete@husky',
                    options: options
                }
            ]);
        },

        // @var Bool saved - defines if saved state should be shown
        setHeaderBar: function(saved) {
            if (saved !== this.saved) {
                if (!!saved) {
                    this.sandbox.emit('sulu.header.toolbar.item.disable', 'save', true);
                } else {
                    this.sandbox.emit('sulu.header.toolbar.item.enable', 'save', false);
                }
            }
            this.saved = saved;
        },

        listenForChange: function() {
            this.sandbox.dom.on('#product-form', 'change', function() {
                this.setHeaderBar(false);
            }.bind(this), 'select');
            this.sandbox.dom.on('#product-form', 'keyup', function() {
                this.setHeaderBar(false);
            }.bind(this), 'input, textarea');
            this.sandbox.on('sulu.content.changed', function() {
                this.setHeaderBar(false);
            }.bind(this));
            this.sandbox.on('husky.select.status.selected.item', function() {
                this.setHeaderBar(false);
            }.bind(this));
            this.sandbox.on('husky.select.orderUnit.selected.item', function() {
                this.setHeaderBar(false);
            }.bind(this));
            this.sandbox.on('husky.select.contentUnit.selected.item', function() {
                this.setHeaderBar(false);
            }.bind(this));
            this.sandbox.on('husky.select.deliveryStatus.selected.item', function() {
                this.setHeaderBar(false);
            }.bind(this));
        }
    };
});
