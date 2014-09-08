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
        
        render = function () {
            this.sandbox.dom.html(this.$el, this.renderTemplate('/admin/product/template/product/items'));

            renderList.call(this);
        },

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
                    url: '/admin/api/products/' + this.options.data.id + '/items?flat=true'
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
        name: 'Sulu Product Items List',

        view: true,

        templates: ['/admin/product/template/product/items'],

        initialize: function () {
            render.call(this);

            setHeaderInformation.call(this);
        }
    };
});
