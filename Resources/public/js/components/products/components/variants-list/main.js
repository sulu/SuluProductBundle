/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define([], function () {
    'use strict';

    var constants = {
            productOverlayName: 'variants'
        },

        maxLengthTitle = 60,

        renderList = function () {
            this.sandbox.sulu.initListToolbarAndList.call(
                this,
                'product-variants-list',
                '/admin/api/products/fields',
                {
                    // TODO use header function instead for consistency
                    el: '#list-toolbar',
                    inHeader: true
                },
                {
                    el: '#product-variants',
                    resultKey: 'products',
                    url: '/admin/api/products/' + this.options.data.id + '/variants?flat=true'
                }
            );
        },

        bindCustomEvents = function () {
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

        initialize: function () {
            render.call(this);

            bindCustomEvents.call(this);

            setHeaderInformation.call(this);
        }
    };
});
