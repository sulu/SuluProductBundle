/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define(
    [
        'mvc/relationalmodel'
    ],
    function (RelationalModel) {

        'use strict';

        function getUrl(urlRoot, productId, productAddonid) {
            return urlRoot + '/' + productId + '/addons' + (productAddonid !== undefined ? '/' + productAddonid : '');
        }

        var productAddon = new RelationalModel({
            urlRoot: '/admin/api/products',

            saveToProduct: function (productId, options) {
                options = _.defaults(
                    (options || {}),
                    {
                        url: getUrl(this.urlRoot, productId)
                    }
                );

                console.error(getUrl(this.urlRoot, productId));
                console.error(options);

                return this.save.call(this, null, options);
            },

            defaults: function() {
                return {
                    addon: 0,
                    prices: []
                };
            }
        });

        return productAddon;
    }
);
