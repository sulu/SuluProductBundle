/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define(function() {

    'use strict';

    return {

        initialize: function() {
            this.bindCustomEvents();
            if (this.options.display === 'list') {
                this.renderList();
            } else if (this.options.display === 'import') {
                this.renderImport();
            }
        },

        /**
         * Creats the view for the flat product list
         */
        renderList: function() {
            var $list = this.sandbox.dom.createElement('<div id="products-list-container"/>');
            this.html($list);
            this.sandbox.start([
                {name: 'products/components/list@suluproduct', options: { el: $list}}
            ]);
        },

        /**
         * Creates the view for the product import
         */
        renderImport: function() {
            var $container = this.sandbox.dom.createElement('<div id="products-import"/>');
            this.html($container);
            this.sandbox.start([
                {name: 'products/components/import@suluproduct', options: { el: $container}}
            ]);
        },

        /**
         * Binds custom-related events
         */
        bindCustomEvents: function() {
            this.sandbox.on('sulu.pim.products.import', function() {
                this.sandbox.emit('sulu.router.navigate', 'pim/products/import');
            }.bind(this));
        }
    };
});
