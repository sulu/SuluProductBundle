/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define([], function() {

    'use strict';

    /**
     * @var ids - array of ids to delete
     * @var callback - callback function returns true or false if data got deleted
     */
    var confirmDeleteDialog = function(callbackFunction) {
        // check if callback is a function
        if (!!callbackFunction && typeof(callbackFunction) !== 'function') {
            throw 'callback is not a function';
        }
        // show dialog
        this.sandbox.emit('sulu.overlay.show-warning',
            'sulu.overlay.be-careful',
            'sulu.overlay.delete-desc',
            callbackFunction.bind(this, false),
            callbackFunction.bind(this, true)
        );
    };

    return {

        /**
         * Shows a dialog when a contact should be deleted
         * @param sandbox
         * @param product
         */
        show: function(sandbox, product) {
            if (!!sandbox && !!product) {
                this.sandbox = sandbox;
                confirmDeleteDialog.call(this, function(wasConfirmed) {
                    if (wasConfirmed) {
                        this.sandbox.emit('sulu.header.toolbar.item.loading', 'options-button');
                        product.destroy({
                            success: function() {
                                this.sandbox.emit('sulu.router.navigate', 'pim/products');
                            }.bind(this)
                        });
                    }
                }.bind(this));
            }
        },

        /**
         * Shows a dialog when removing media from a product
         */
        showMediaRemoveDialog: function(sandbox, callback) {
            this.sandbox = sandbox;
            confirmDeleteDialog.call(this, callback);
        }
    };
});
