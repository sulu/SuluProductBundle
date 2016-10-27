/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define(['mvc/collection', 'suluproduct/models/currency'], function(Collection, Currency) {
    'use strict';

    return new Collection({
        model: Currency,

        locale: null,

        initialize: function(options) {
            this.setLocale(options.locale);
        },

        setLocale: function(locale) {
            this.locale = locale;
        },

        parse: function(response) {
            if (!!response._embedded) {
                return response._embedded.currencies;
            } else {
                return response;
            }
        },

        url: function() {
            return '/admin/api/currencies?flat=true&locale=' + this.locale;
        }
    });
});
