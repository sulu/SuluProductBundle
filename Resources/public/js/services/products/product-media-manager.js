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

    var baseUrl = '/admin/api/products/%s/media',

        /**
         * Returns product media url.
         *
         * @param {Number} productId
         * @param {Number} mediaId
         */
        getUrl = function(productId, mediaId) {
            var url = baseUrl.replace('%s', productId);
            if (!!mediaId) {
                url += '/' + mediaId;
            }

            return url;
        };

    return {
        /**
         * Function for saving new product media relations.
         *
         * @param {Number} productId
         * @param {Object} data
         *
         * @returns {Object}
         */
        save: function(productId, data) {
            var method = 'PUT';

            return Util.save(
                getUrl(productId, data.id),
                method,
                data
            );
        },

        /**
         * Function for deleting product media relation(s).
         *
         * @param {Number} productId
         * @param {Number} mediaId
         *
         * @returns {Object}
         */
        delete: function(productId, mediaId) {
            return Util.save(
                getUrl(productId, mediaId),
                'DELETE'
            );
        }
    }
});
