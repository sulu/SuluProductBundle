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

    return {

        /**
         * initializes the header toolbar
         * @param sandbox
         * @param status
         */
        initToolbar: function(sandbox, status) {
            this.sandbox = sandbox;
            this.bindCustomEvents(status);
        },

        bindCustomEvents: function(status) {
            this.sandbox.once('husky.toolbar.header.initialized', function() {
                this.setStatus(status);
            }.bind(this));

            this.sandbox.off('product.state.change');
            this.sandbox.on('product.state.change', function(status) {
                this.setStatus(status);
            }.bind(this))
        },

        setStatus: function(status) {
            var statusTitle = this.sandbox.translate(Config.get('product.status.inactive').key),
                statusIcon = 'husky-test',
                buttonDefaults;

            // new product or existing active
            if (!status || status.id === Config.get('product.status.active').id) {
                statusTitle = this.sandbox.translate(Config.get('product.status.active').key);
                statusIcon = 'husky-publish';
            }

            buttonDefaults = {
                title: statusTitle,
                icon: statusIcon
            };

            this.sandbox.emit('sulu.header.toolbar.button.set', 'productWorkflow', buttonDefaults);
        }
    };
});
