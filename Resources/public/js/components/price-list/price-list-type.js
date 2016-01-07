/*
 * This file is part of the Husky Validation.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 *
 */

define([
    'type/default',
    'form/util'
], function(Default) {

    'use strict';

    return function($el, options) {
        var defaults = {
                id: 'id',
                label: 'value',
                required: false,
                formId : '#prices-form'
            },

            typeInterface = {
                setValue: function(data) {
                    if (data === undefined || data === '' || data === null) {
                        return;
                    }

                    if (typeof data === 'object') {
                        App.dom.data(this.$el, 'prices', data);
                    }
                },

                getValue: function() {
                    return App.dom.data(this.$el, 'prices');
                },

                needsValidation: function() {
                    var val = this.getValue();
                    return !!val;
                },

                validate: function() {
                    return true;
                }
            };

        return new Default($el, defaults, options, 'price-list', typeInterface);
    };
});
