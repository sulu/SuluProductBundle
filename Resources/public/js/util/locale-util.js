/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define(['config'], function(Config) {

    'use strict';

    /**
     * Returns all product locales and parses them for dropdown.
     *
     * @returns []
     */
    var getProductLocalesForDropdown = function() {
        var productConfig = Config.get('sulu-product');

        // Map to dropdown specific format.
        return _.map(productConfig['locales'], function(value) {
            return {
                id: value,
                title: value
            };
        });
    };

    /**
     * Function returns the locale that should be used by default for current user.
     * If users locale matches any of the given product locales, that one is taken
     * as default.
     * If locale does not match exactly, the users language is compared as well.
     * If there are no matches at all, the default-locale as defined in the config
     * is returned.
     *
     * @returns {String}
     */
    var retrieveDefaultLocale = function(sandbox) {
        var user = sandbox.sulu.user;
        var productConfig = Config.get('sulu-product');
        var defaultLocale = productConfig['fallback_locale'];
        var locales = productConfig['locales'];
        var languageMatch = null;

        // Check if users locale contains localization.
        var userLanguage = user.locale.substring(0, user.locale.indexOf('_'));

        for (var i = -1, len = locales.length; ++i <len;) {
            var current = locales[i];

            // If locale matches users locale, the exact matching was found.
            if (user.locale == current) {
                return current;
            }

            // Check if users language (without locale) matches.
            if (userLanguage == current) {
                languageMatch = current;
            }
        }

        // If language matches.
        if (languageMatch) {
            return languageMatch;
        }

        // Return default locale if no match was found.
        return defaultLocale;
    };

    return {
        retrieveDefaultLocale: function(sandbox) {
            return retrieveDefaultLocale(sandbox);
        },

        getProductLocalesForDropdown: function() {
            return getProductLocalesForDropdown();
        }
    };
});
