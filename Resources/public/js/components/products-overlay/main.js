/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define([], function () {
    'use strict';

    var defaults = {
            translations: {
                addProducts: 'products-overlay.add-products'
            }
        },

        eventNamespace = 'sulu.products.products-overlay.',

        /** returns normalized event names */
        createEventName = function (postFix) {
            return eventNamespace + (this.options.instanceName ? this.options.instanceName + '.' : '') + postFix;
        },

        OPEN = function () {
            return createEventName.call(this, 'open');
        },

        initializeOverlay = function () {
            var $el = this.sandbox.dom.createElement('<div/>');
            this.sandbox.dom.append(this.$el, $el);

            this.sandbox.start([
                {
                    name: 'overlay@husky',
                    options: {
                        el: $el,
                        instanceName: this.options.instanceName,
                        slides: [
                            {
                                title: this.sandbox.translate(this.options.translations.addProducts)
                            }
                        ]
                    }
                }
            ]);

            bindCustomEvents.call(this);
        },

        openOverlay = function () {
            this.sandbox.emit('husky.overlay.' + this.options.instanceName + '.open');
        },

        bindCustomEvents = function () {
            this.sandbox.on(OPEN.call(this), function () {
                openOverlay.call(this);
            }, this);
        };

    return {
        initialize: function () {
            this.options = this.sandbox.util.extend({}, defaults, this.options);

            initializeOverlay.call(this);
        }
    };
});
