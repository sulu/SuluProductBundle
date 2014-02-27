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
            if (this.options.display === 'list') {
                this.renderList();
            }
        },

        /**
         * Creats the view for the flat product list
         */
        renderList: function() {
            var $list = this.sandbox.dom.createElement('<div id="products-list-container"/>');
            this.html($list);
            this.sandbox.start([
                {name: 'products/components/list@suluproductbase', options: { el: $list}}
            ]);
        }
    };
});
