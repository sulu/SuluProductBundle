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

    var baseUrl = '/admin/api/products/%s/seo?locale=%s',

        /**
         * Returns product seo url.
         *
         * @param {Number} productId
         * @param {String} locale
         */
        getUrl = function(productId, locale) {
            var url = baseUrl.replace('%s', productId).replace('%s', locale);

            return url;
        };

    return {
        /**
         * Function for saving new product media relations.
         *
         * @param {Number} productId
         * @param {Object} data
         * @param {String} locale
         *
         * @returns {Object}
         */
        save: function(productId, data, locale) {
            var method = 'PUT';

            return Util.save(
                getUrl(productId, data, locale),
                method,
                data
            );
        }
    }
});
