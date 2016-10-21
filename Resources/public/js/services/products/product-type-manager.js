/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define([], function() {

    'use strict';

    /** @constructor **/
    function ProductTypeManager() {}

    ProductTypeManager.prototype = {

        types: {
            'PRODUCT': 1,
            'PRODUCT_WITH_VARIANTS': 2,
            'PRODUCT_ADDON': 3,
            'PRODUCT_SET': 4,
            'PRODUCT_VARIANT': 5
        },

        /**
         * Returns type by given key.
         *
         * @param {String} key
         */
        getTypeByKey: function(key) {
            if (!this.types.hasOwnProperty(key)) {
                return null;
            }

           return this.types[key];
        }
    };

    return new ProductTypeManager();
});
