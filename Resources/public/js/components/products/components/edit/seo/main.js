/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define(['services/product/product-seo-manager'], function(ProductSeoManager) {

    'use strict';

    return {

        type: 'seo-tab',

        /**
         * {@inheritdoc}
         */
        parseData: function(data) {
            //return data.ext.seo;

            console.warn(data);
        },

        /**
         * {@inheritdoc}
         */
        getUrl: function() {
            var content = this.options.data();

            return this.options.excerptUrlPrefix + content.route;
        },

        /**
         * {@inheritdoc}
         */
        save: function(data, action) {
            var content = this.options.data();
            content.ext.seo = data;

            ProductSeoManager.save(content, this.options.locale, action).then(function(response) {
                this.sandbox.emit('sulu.tab.saved', response.id, response);
            }.bind(this)).fail(function(xhr) {
                this.sandbox.emit('sulu.product.error', xhr.status, data);
            }.bind(this));
        }
    };
});
