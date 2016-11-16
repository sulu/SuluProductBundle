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

    var baseUrl = '/admin/api/products',

        /**
         * Creates url based on given data.
         *
         * @param {Object} data
         * @param {String} locale
         * @param {String} action
         *
         * @returns {string}
         */
        getUrl = function(data, locale, action) {
            var url = baseUrl;
            var requestParameters = [];

            if (!!data.id) {
                url += '/' + data.id;
            }

            if (!!locale) {
                requestParameters.push('locale=' + locale);
            }

            if (!!action) {
                requestParameters.push('action=' + action);
            }

            return url + '?' + requestParameters.join('&');
        };

    return {
        /**
         * Saves product by product data.
         *
         * @param {Number} data
         * @param {String} locale
         * @param {String} action
         * @param {String} method
         *
         * @returns {Object}
         */
        save: function(data, locale, action, method) {
            if (!method) {
                if (!!data.id) {
                    method = 'PUT';
                } else {
                    method = 'POST';
                }
            }

            return Util.save(
                getUrl(data, locale, action),
                method,
                data
            );
        },

        /**
         * Saves status by performing patch to product api.
         *
         * @param {Number} productId
         * @param {Object} status
         *
         * @returns {Object}
         */
        saveStatus: function(productId, status) {
            return Util.save(
                getUrl({id: productId}),
                'PATCH',
                {
                    status: status
                }
            )
        },

        /**
         * Deletes product with the given id.
         *
         * @param {Number} productId
         *
         * @returns {Object}
         */
        delete: function(productId) {
            return Util.save(
                getUrl({id: productId}),
                'DELETE'
            );
        }
    }
});
