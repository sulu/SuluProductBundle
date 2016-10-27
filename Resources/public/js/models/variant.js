/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define(['mvc/relationalmodel'], function(RelationalModel) {

        'use strict';

        var variant = new RelationalModel({
            productId: null,
            locale: null,
            ids: null,
            flat: false,

            /**
             * Returns url for variant model.
             *
             * @returns {string}
             */
            url: function() {
                var url = '/admin/api/products/' + this.productId + '/variants';
                var params = {};

                if (this.id) {
                    url += '/' + this.id;
                }

                // Handle parameters.
                if (this.ids && this.ids.length > 0) {
                    params.ids = this.ids.join(',');
                }
                if (this.locale) {
                    params.locale = this.locale;
                }
                if (this.flat) {
                    params.flat = this.flat;
                }
                if (Object.keys(params).length) {
                    url += '?' + $.param(params);
                }

                return url;
            },

            /**
             * {@inheritdoc}
             */
            destroy: function(options) {
                var options = $.extend(true, {
                    method: 'DELETE'
                }, options);

                $.ajax(this.url(), options);
            },

            /**
             * {@inheritdoc}
             */
            initialize: function(options) {
                this.locale = options.locale;
                this.productId = options.productId;
                this.ids = options.ids;
                this.flat = options.flat;
            },

            /**
             * {@inheritdoc}
             */
            defaults: function() {
                return {
                    id: null,
                    ids: null,
                    name: '',
                    number: '',
                    attributes: [],
                    prices: []
                };
            }
        });

        return variant;
    }
);
