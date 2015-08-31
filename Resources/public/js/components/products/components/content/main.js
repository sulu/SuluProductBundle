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
                                        title: 'product.workfow.set.active',
                                        callback: function() {
                                            app.sandbox.emit(
                                                'product.state.change',
                                                {id: Config.get('product.status.active').id}
                                            );
                                        }
                                    },
                                    {
                                        id: 'inactive',
                                        title: 'product.workfow.set.inactive',
                                        callback: function() {
                                            app.sandbox.emit(
                                                'product.state.change',
                                                {id: Config.get('product.status.inactive').id}
                                            );
                                        }
                                    }
                                ]
                            }
                        }
                    },
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
