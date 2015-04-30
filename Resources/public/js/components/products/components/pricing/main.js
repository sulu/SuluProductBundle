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

    var formSelector = '#product-pricing-form',
        pricesSelector = '#prices',
        maxLengthTitle = 60,

        render = function () {
            this.sandbox.dom.html(this.$el, this.renderTemplate('/admin/product/template/product/pricing'));

            setHeaderInformation.call(this);

            initForm.call(this, this.options.data);

            var PriceListData = [];

            if (!!this.options.data.prices) {
                PriceListData.prices = this.options.data.prices;
            }

            if (!!this.options.data.specialPrices) {
                PriceListData.specialPrices = this.options.data.specialPrices;
            }

            initPriceList.call(this, PriceListData);
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

        bindCustomEvents = function () {
            this.sandbox.on('sulu.header.toolbar.delete', function () {
                this.sandbox.emit('sulu.product.delete', this.sandbox.dom.val('#id'));
            }.bind(this));

            this.sandbox.on('product.state.change', function(id) {
                if (!this.options.data.status || this.options.data.status.id !== id) {
                    this.status = {id: id};
                    this.options.data.status = this.status;
                    setHeaderBar.call(this, false);
                }
            }, this);

            this.sandbox.on('sulu.header.toolbar.save', function () {
                save.call(this);
            }, this);

            this.sandbox.on('sulu.products.saved', function(data) {
                setHeaderBar.call(this, true);
                this.options.data = data;
                this.options.data.status = this.status;
            }, this);

            this.sandbox.on('sulu.header.back', function () {
                this.sandbox.emit('sulu.products.list');
            }, this);

            this.sandbox.on('sulu.product.set-currencies', function(currencies){
                this.currencies = currencies;
            }, this);
            this.sandbox.on('sulu.product.set-default-currency', function(cur){
                this.defaultCurrency = cur;
            }, this);
        },

        save = function () {
            if (this.sandbox.form.validate(formSelector)) {
                var data = this.sandbox.form.getData(formSelector);
                data.status = this.status;
                this.sandbox.emit('sulu.products.save', data);
            }
        },

    // TODO remove the following functions, as soon as they are extracted somewhere else
        initForm = function (data) {
            // set form data
            var formObject = this.sandbox.form.create(formSelector);
            formObject.initialized.then(function () {
                setFormData.call(this, data);
            }.bind(this));
        },

        setFormData = function (data) {
            this.sandbox.form.setData(formSelector, data).then(function () {
                this.sandbox.start(formSelector);
            }.bind(this)).fail(function (error) {
                this.sandbox.logger.error("An error occured when setting data!", error);
            }.bind(this));
        },

        setHeaderBar = function(saved) {
            if (saved !== this.saved) {
                var type = (!!this.options.data && !!this.options.data.id) ? 'edit' : 'add';
                this.sandbox.emit('sulu.header.toolbar.state.change', type, saved, true);
            }
            this.saved = saved;
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

        listenForChange = function () {
            this.sandbox.dom.on(formSelector, 'change', function () {
                setHeaderBar.call(this, false);
            }.bind(this), 'select');
            this.sandbox.dom.on(formSelector, 'keyup', function () {
                setHeaderBar.call(this, false);
            }.bind(this), 'input, textarea');
            this.sandbox.on('sulu.content.changed', function () {
                setHeaderBar.call(this, false);
            }.bind(this));
            this.sandbox.on('husky.select.tax-class.selected.item', function () {
                setHeaderBar.call(this, false);
            }.bind(this));
        };

    return {
        name: 'Sulu Product Pricing View',

        view: true,

        templates: ['/admin/product/template/product/pricing'],

        initialize: function () {
            this.status  = !!this.options.data ? this.options.data.status : Config.get('product.status.active');

            bindCustomEvents.call(this);

            render.call(this);

            listenForChange.call(this);
        }
    };
});
