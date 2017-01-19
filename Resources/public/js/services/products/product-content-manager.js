/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define(['jquery', 'services/husky/util'], function($, Util) {
    'use strict';

    var baseUrl = '/admin/api/products/%s/content?locale=%s',

        /**
         * Returns product media url.
         *
         * @param {Number} productId
         * @param {Number} locale
         */
        getUrl = function(productId, locale) {
            var url = baseUrl.replace('%s', productId).replace('%s', locale);

            return url;
        };

    return {
        /**
         * Loads content for a specific product id.
         *
         * @param {Number} productId
         * @param {String} locale
         *
         * @returns {Object}
         */
        load: function(productId, locale) {
            return Util.load(getUrl(productId, locale));
        },

        /**
         * Function for saving new product media relations.
         *
         * @param {Number} productId
         * @param {String} locale
         * @param {Object} data
         *
         * @returns {Object}
         */
        save: function(productId, locale, data) {
            var method = 'PUT';

            return Util.save(
                getUrl(productId, locale),
                method,
                data
            );
        }
    }
});
