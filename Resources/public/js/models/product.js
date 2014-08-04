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
        urlRoot: '/admin/api/products',

        fetchLocale: function (locale, options) {
            options = _.defaults((options || {}),
                {
                    url: this.urlRoot + (this.get('id') !== undefined ? '/' + this.get('id') : '') + '?locale=' + locale
                }
            );

            return this.fetch.call(this, options);
        },

        defaults: function () {
            return {
                name: '',
                code: '',
                number: ''
            };
        }
    });
});
