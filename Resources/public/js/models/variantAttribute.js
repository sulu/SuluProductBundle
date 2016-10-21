/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define(['mvc/relationalmodel'], function (RelationalModel) {

    'use strict';

    return new RelationalModel({
        urlRoot: function() {
            return '/admin/api/products/' + this.productId + '/variant-attributes';
        },

        initialize: function(options) {
            this.productId = options.productId;
        },

        defaults: function () {
            return {
                id: null,
                productId: null,
                attributeId: null
            };
        }
    });
});
