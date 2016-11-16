/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

require.config({
    paths: {
        suluproduct: '../../suluproduct/js',
        suluproductcss: '../../suluproduct/css',
        'suluproduct/util/header': '../../suluproduct/js/components/products/util/header',
        'suluproduct/util/locale-util': '../../suluproduct/js/util/locale-util',
        'suluproduct/util/price-calculation-util':'../../suluproduct/js/util/price-calculation-util',
        'suluproduct/util/product-delete-dialog': '../../suluproduct/js/components/products/util/product-delete-dialog',
        'type/price-list': '../../suluproduct/js/components/price-list/price-list-type',
        'extensions/sulu-buttons-productbundle': '../../suluproduct/js/extensions/sulu-buttons',
        'type/product-selection': '../../suluproduct/js/validation/types/product-selection',
        'services/product/product-manager': '../../suluproduct/js/services/products/product-manager',
        'services/product/product-type-manager': '../../suluproduct/js/services/products/product-type-manager',
        'services/product/product-media-manager': '../../suluproduct/js/services/products/product-media-manager',
        'services/product/product-content-manager': '../../suluproduct/js/services/products/product-content-manager'
    }
});

define([
    'config',
    'extensions/sulu-buttons-productbundle',
    'css!suluproductcss/main'
], function(Config, ProductButtons) {

    'use strict';

    return {
        name: 'SuluProductBundle',

        initialize: function(app) {

            var sandbox = app.sandbox;

            sandbox.sulu.buttons.push(ProductButtons.getButtons());

            Config.set('product.status.active', {id: 3, key: 'product.workflow.set.active'});
            Config.set('product.status.inactive', {id: 5, key: 'product.workflow.set.inactive'});
            Config.set('product.list.statuses.ids', [3, 5]);

            Config.set('suluproduct.components.autocomplete.default', {
                remoteUrl: '/admin/api/products?flat=true&searchFields=number,name,supplier&fields=id,name,number,supplier',
                resultKey: 'products',
                getParameter: 'search',
                value: '',
                instanceName: 'products',
                valueKey: 'name',
                noNewValues: true,
                fields: [
                    {
                        id: 'number',
                        width: '60px'
                    },
                    {
                        id: 'name',
                        width: '480px'
                    },
                    {
                        id: 'supplier',
                        width: '150px'
                    }
                ]
            });

            Config.set('suluresource.filters.type.products', {
                breadCrumb: [
                    {title: 'navigation.pim'},
                    {title: 'pim.products.title', link: 'pim/products'}
                ],
                routeToList: 'pim/products'
            });

            app.components.addSource('suluproduct', '/bundles/suluproduct/js/components');

            //flat list of products
            sandbox.mvc.routes.push({
                route: 'pim/products',
                callback: function() {
                    return '<div data-aura-component="products@suluproduct" data-aura-display="list"/>';
                }
            });

            sandbox.mvc.routes.push({
                route: 'pim/products/:locale/add/type::type',
                callback: function(locale, type) {
                    return '<div data-aura-component="products@suluproduct" data-aura-display="tab" data-aura-locale="' + locale + '" data-aura-product-type="' + type + '"/>';
                }
            });

            sandbox.mvc.routes.push({
                route: 'pim/products/:locale/edit::id/:content',
                callback: function(locale, id, content) {
                    return '<div data-aura-component="products@suluproduct" data-aura-display="tab" data-aura-content="' + content + '" data-aura-locale="' + locale + '" data-aura-id="' + id + '"/>';
                }
            });

            sandbox.mvc.routes.push({
                route: 'pim/products/import',
                callback: function() {
                    return '<div data-aura-component="products@suluproduct" data-aura-display="import"/>';
                }
            });

            //flat list of attributes
            sandbox.mvc.routes.push({
                route: 'pim/attributes',
                callback: function() {
                    return '<div data-aura-component="attributes@suluproduct" data-aura-display="list"/>';
                }
            });

            sandbox.mvc.routes.push({
                route: 'pim/attributes/:locale/add',
                callback: function(locale, type) {
                    return '<div data-aura-component="attributes@suluproduct" data-aura-display="form" data-aura-locale="' + locale + '"/>';
                }
            });

            sandbox.mvc.routes.push({
                route: 'pim/attributes/:locale/edit::id/:details',
                callback: function(locale, id) {
                    return '<div data-aura-component="attributes@suluproduct" data-aura-display="form" data-aura-locale="' + locale + '" data-aura-id="' + id + '"/>';
                }
            });
        }
    };
});
