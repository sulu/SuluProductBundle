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
        'mvc/relationalmodel',
        'mvc/hasmany',
        'suluproduct/collections/variants'
    ],
    function (RelationalModel, HasMany, Variants) {

        'use strict';

        function getUrl(urlRoot, id, locale) {
            return urlRoot + (id !== undefined ? '/' + id : '') + '?locale=' + locale;
        }

        var product = new RelationalModel({
            urlRoot: '/admin/api/products',

            relations: [
                {
                    type: HasMany,
                    key: 'variants',
                    relatedModel: function () {
                        return product;
                    },
                    collectionType: Variants,
                    reverseRelation: {
                        key: 'parent'
                    }
                }
            ],

            saveLocale: function (locale, options) {
                options = _.defaults(
                    (options || {}),
                    {
                        url: getUrl(this.urlRoot, this.get('id'), locale)
                    }
                );

                return this.save.call(this, null, options);
            },

            fetchLocale: function (locale, options) {
                options = _.defaults((options || {}),
                    {
                        url: getUrl(this.urlRoot, this.get('id'), locale)
                    }
                );

                var result = this.fetch.call(this, options);

                this.get('variants').setProductId(this.get('id'));

                return result;
            },

            defaults: function () {
                return {
                    name: '',
                    code: '',
                    number: '',
                    variants: []
                };
            }
        });

        return product;
    }
);
