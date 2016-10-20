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

            url: function() {
                var url = '/admin/api/products/' + this.productId + '/variants';

                if (this.id) {
                    url += '/' + this.id;
                }

                return url + '?locale=' + this.locale;
            },

            initialize: function(options) {
                this.locale = options.locale;
                this.productId = options.productId;
            },

            defaults: function() {
                return {
                    id: '',
                    name: '',
                    number: '',
                    attributes: [],
                    prices: [],
                };
            }
        });

        return variant;
    }
);
