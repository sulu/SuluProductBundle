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

    var maxLengthTitle = 60,
        
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
                    url: '/admin/api/products/' + this.options.data.id + '/variants?flat=true',
                    viewOptions: {
                        table: {
                            fullWidth: true
                        }
                    }
                }
            );
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
        };

    return {
        name: 'Sulu Product Variants List',

        view: true,

        layout: {
            content: {
                width: 'max',
                leftSpace: false,
                rightSpace: false
            }
        },

        templates: ['/admin/product/template/product/variants'],

        initialize: function () {
            this.render();

            setHeaderInformation.call(this);
        },

        render: function () {
            this.sandbox.dom.html(this.$el, this.renderTemplate('/admin/product/template/product/variants'));

            renderList.call(this);
        }
    };
});
