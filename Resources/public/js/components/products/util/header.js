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
     * @param {Boolean} isActive Defines if saved state should be shown.
     */
    var setSaveButton = function(isActive) {
            if (!isActive) {
                this.sandbox.emit('sulu.header.toolbar.item.disable', 'save', true);
            } else {
                this.sandbox.emit('sulu.header.toolbar.item.enable', 'save', false);
            }
        },

        /**
         * Bind all custom events.
         */
        bindCustomEvents = function() {
            this.sandbox.on('sulu.toolbar.delete', onDeleteClicked.bind(this));

            this.sandbox.once('husky.toolbar.header.initialized', setStatus.bind(this, this.status));

            this.sandbox.off('product.state.change');
            this.sandbox.on('product.state.change', onStatusChanged.bind(this));
        },

        /**
         * Called when delete button is clicked.
         */
        onDeleteClicked = function(productId) {
            this.sandbox.emit('sulu.product.delete', productId);
        },

        /**
         * Called when status in toolbar has been changed.
         *
         * @param {Object} status
         */
        onStatusChanged = function(status) {
            // Change status if it differs to previous status.
            if (this.status.id !== status.id) {
                this.status = status;
                setSaveButton.call(this, true);
                setStatus.call(this, status);
            }
        },

        /**
         * Function that sets initial status of toolbar status dropdown.
         *
         * @param {Object} status
         */
        setStatus = function(status) {
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
        };

    return {
        /**
         * Initializes the header toolbar.
         *
         * @param {Object} sandbox
         * @param {Object} status
         */
        initToolbar: function(sandbox, status) {
            this.sandbox = sandbox;
            this.status = status;
            this.initialStatus = status;

            bindCustomEvents.call(this, status);
            setStatus.call(this, status);
        },

        /**
         * Returns the currently selected status.
         *
         * @returns {Object}
         */
        getSelectedStatus: function() {
            return this.status;
        },

        /**
         * If status has changed the status is returned, otherwise, false is returned.
         *
         * @returns {Bool|Object}
         */
        retrieveChangedStatus: function() {
            if (this.initialStatus !== this.status) {
                return this.status;
            }

            return false;
        },

        /**
         * Sets save button to active or inactive.
         *
         * @param {Bool} isActive
         */
        setSaveButton: function(isActive) {
            setSaveButton.call(this, isActive);
        }
    };
});
