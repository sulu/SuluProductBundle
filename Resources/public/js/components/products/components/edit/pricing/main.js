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

    var formSelector = '#product-pricing-form',
        pricesSelector = '#prices',

        render = function() {
            this.sandbox.dom.html(this.$el, this.renderTemplate('/admin/product/template/product/pricing'));

            initForm.call(this, this.options.data);
            initPriceList.call(this, this.options.data);
            setHeaderBar.call(this, true);
        },

        initPriceList = function(data) {
            var options = {
                currencies: this.currencies,
                defaultCurrency: this.defaultCurrency,
                data: data,
                el: pricesSelector
            };

            this.sandbox.start([{
                name: 'price-list@suluproduct',
                options: options
            }]);
        },

        bindCustomEvents = function() {
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
                save.call(this);
            }, this);

            this.sandbox.on('sulu.products.saved', function() {
                setHeaderBar.call(this, true);
            }, this);

            this.sandbox.on('sulu.product.set-currencies', function(currencies) {
                this.currencies = currencies;
            }, this);
            this.sandbox.on('sulu.product.set-default-currency', function(cur) {
                this.defaultCurrency = cur;
            }, this);
        },

        save = function() {
            if (this.sandbox.form.validate(formSelector)) {
                var data = this.sandbox.form.getData(formSelector);
                data.status = this.status;
                this.sandbox.emit('sulu.products.save', data);
            }
        },

    // TODO remove the following functions, as soon as they are extracted somewhere else
        initForm = function(data) {
            // set form data
            var formObject = this.sandbox.form.create(formSelector);
            formObject.initialized.then(function() {
                setFormData.call(this, data);
            }.bind(this));
        },

        setFormData = function(data) {
            this.sandbox.form.setData(formSelector, data.toJSON()).then(function() {
                this.sandbox.start(formSelector);
            }.bind(this)).fail(function(error) {
                this.sandbox.logger.error("An error occured when setting data!", error);
            }.bind(this));
        },

        // @var Bool saved - defines if saved state should be shown
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

        listenForChange = function() {
            this.sandbox.dom.on(formSelector, 'change', function() {
                setHeaderBar.call(this, false);
            }.bind(this), 'input[type="checkbox"], select');
            this.sandbox.dom.on(formSelector, 'keyup', function() {
                setHeaderBar.call(this, false);
            }.bind(this), 'input, textarea');
            this.sandbox.on('sulu.content.changed', function() {
                setHeaderBar.call(this, false);
            }.bind(this));
            this.sandbox.on('husky.select.tax-class.selected.item', function() {
                setHeaderBar.call(this, false);
            }.bind(this));
        };

    return {
        name: 'Sulu Product Pricing View',

        view: true,

        templates: ['/admin/product/template/product/pricing'],

        initialize: function() {
            if (!!this.options.data) {
                this.status = this.options.data.attributes.status;
            } else {
                this.status = Config.get('product.status.inactive');
            }

            // reset status if it has been changed before and has not been saved
            this.sandbox.emit('product.state.change', this.status);
            bindCustomEvents.call(this);

            render.call(this);

            listenForChange.call(this);
        }
    };
});
