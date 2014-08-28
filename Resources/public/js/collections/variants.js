/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define(['mvc/collection', 'suluproduct/models/product'], function (Collection, Product) {
    'use strict';

    return new Collection({
        model: Product,

        productId: null,

        setProductId: function (productId) {
            this.productId = productId;
        },

        url: function () {
            return '/admin/api/products/' + this.productId + '/variants';
        }
    });
});
