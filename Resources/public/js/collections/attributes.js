/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define(['mvc/collection', 'suluproduct/models/attribute'], function (Collection, Attribute) {
    'use strict';

    return new Collection({
        model: Attribute,

        locale: 'en',

        initialize: function(options) {
            this.setLocale(options.locale);
        },

        setLocale: function(locale) {
            this.locale = locale;
        },

        parse: function(response) {
            if (!!response._embedded) {
                return response._embedded.attributes;
            } else {
                return response;
            }
        },

        url: function () {
            return '/admin/api/attributes?limit=500&locale=' + this.locale;
        }
    });
});
