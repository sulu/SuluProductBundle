/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define([], function() {

    'use strict';

    return {

        /**
         * Updates the product data
         * @returns {Object} returns a promise
         */
        update: function(sandbox) {
            this.sandbox = sandbox;

            var dfdProductUpdate = this.sandbox.data.deferred();
            this.sandbox.on('sulu.products.product-update', function(data) {
                dfdProductUpdate.resolve(data);
            }.bind(this));

            this.sandbox.emit('sulu.products.get-product-update');
            return dfdProductUpdate.promise();
        }
    };
});
