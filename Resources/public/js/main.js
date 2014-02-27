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
        suluproductbase: '../suluproductbase/js'
    }
});

define({

    name: "SuluProductBaseBundle",

    initialize: function(app) {

        'use strict';

        var sandbox = app.sandbox;

        app.components.addSource('suluproductbase', '/bundles/suluproductbase/js/components');

        //flat list of products
        sandbox.mvc.routes.push({
            route: 'pim/products',
            callback: function() {
                this.html('<div data-aura-component="products@suluproductbase" data-aura-display="list"/>');
            }
        });
    }
});
