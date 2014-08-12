/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

require.config({
    paths: {
        suluproduct: '../../suluproduct/js'
    }
});

define({

    name: "SuluProductBundle",

    initialize: function(app) {

        'use strict';

        var sandbox = app.sandbox;

        app.components.addSource('suluproduct', '/bundles/suluproduct/js/components');

        //flat list of products
        sandbox.mvc.routes.push({
            route: 'pim/products',
            callback: function() {
                this.html('<div data-aura-component="products@suluproduct" data-aura-display="list"/>');
            }
        });

        sandbox.mvc.routes.push({
            route: 'pim/products/:locale/add/type::type',
            callback: function(locale, type) {
                this.html('<div data-aura-component="products/components/content@suluproduct" data-aura-display="form" data-aura-locale="' + locale + '" data-aura-product-type="' + type + '"/>');
            }
        });

        sandbox.mvc.routes.push({
            route: 'pim/products/:locale/edit::id/:content',
            callback: function(locale, id, content) {
                this.html('<div data-aura-component="products/components/content@suluproduct" data-aura-display="form" data-aura-content="' + content + '" data-aura-locale="' + locale + '" data-aura-id="' + id + '"/>');
            }
        });

        sandbox.mvc.routes.push({
            route: 'pim/products/import',
            callback: function() {
                this.html('<div data-aura-component="products@suluproduct" data-aura-display="import"/>');
            }
        });
    }
});
