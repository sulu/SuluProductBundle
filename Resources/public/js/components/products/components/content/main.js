/*
 * This file is part of the Sulu CMF.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define(['config'], function(Config) {

    'use strict';

    /**
     * Returns all product locales.
     *
     * @returns []
     */
    var getProductLocales = function() {
        var productConfig = Config.get('sulu-product');

        // Map to dropdown specific format.
        return _.map(productConfig['locales'], function(value) {
            return {
                id: value,
                title: value
            };
        });
    };

    return {
        header: function() {
            return {
                toolbar: {
                    buttons: {
                        save: {},
                        delete: {},
                        productWorkflow: {
                            options: {
                                disabled: false,
                                dropdownItems: [
                                    {
                                        id: 'active',
                                        title: 'product.workflow.set.active',
                                        callback: function() {
                                            app.sandbox.emit(
                                                'product.state.change',
                                                Config.get('product.status.active')
                                            );
                                        }
                                    },
                                    {
                                        id: 'inactive',
                                        title: 'product.workflow.set.inactive',
                                        callback: function() {
                                            app.sandbox.emit(
                                                'product.state.change',
                                                Config.get('product.status.inactive')
                                            );
                                        }
                                    }
                                ]
                            }
                        }
                    },
                    languageChanger: {
                        data: getProductLocales(),
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
