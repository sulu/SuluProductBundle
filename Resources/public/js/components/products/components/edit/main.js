/*
 * This file is part of the Sulu CMF.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define([
    'config',
    'suluproduct/util/locale-util',
    'services/sulupreview/preview'
], function(Config, LocaleUtil, Preview) {

    'use strict';

    return {
        layout: function() {
            return {
                content: {
                    width: 'fixed',
                    leftSpace: true,
                    rightSpace: true
                },
                sidebar: (!!this.options.id) ? 'max' : false
            }
        },

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
                    url: '/admin/content-navigations?alias=' + this.options.productType,
                    options: {
                        getPreview: this.getPreview.bind(this)
                    }
                }
            };
        },

        /**
         * Initialization function of edit product component.
         */
        initialize: function() {
            this.preview = null;
            this.initializePreview();
        },

        /**
         * Starts the preview component if product already exists.
         */
        initializePreview: function() {
            if (!this.options.id) {
                return;
            }

            // Start Preview component.
            this.preview = Preview.initialize({});
            this.preview.start(
                'Sulu\\Bundle\\ProductBundle\\Entity\\ProductTranslation',
                this.options.id,
                this.options.locale,
                this.options.data
            );
        },

        /**
         * Callback for receiving preview object.
         *
         * @returns {Object}
         */
        getPreview: function() {
            return this.preview;
        }
    };
});
