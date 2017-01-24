/*
 * This file is part of the Sulu CMF.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define(['config', 'suluproduct/util/locale-util'], function(Config, LocaleUtil) {

    'use strict';

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
                        data: LocaleUtil.getProductLocalesForDropdown(),
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
