/*
 * This file is part of the Sulu CMF.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define(function () {

    'use strict';

    return {
        // TODO exclude tabs into own function and remove header
        header: function () {
            return {
                toolbar: {
                    template: 'default',
                    languageChanger: {
                        preSelected: this.options.locale
                    }
                },
                tabs: {
                    url: '/admin/content-navigations?alias=' + this.options.productType
                }
            };
        }
    };
});
