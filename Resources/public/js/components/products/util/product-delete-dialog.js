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
     * @param {Function} callbackFunction Callback function returns true or false if data got deleted.
     * @param {Number} numberOfVariants
     */
    var confirmDeleteDialog = function(callbackFunction, numberOfVariants) {
        // Check if callback is a function.
        if (!!callbackFunction && typeof(callbackFunction) !== 'function') {
            throw 'callback is not a function';
        }

        var warningMessage = 'sulu.overlay.delete-desc';

        if (numberOfVariants > 0) {
            warningMessage = this.sandbox.translate('sulu_product.dialog.delete-product-with-variants');
            warningMessage = warningMessage.replace('%number%', numberOfVariants.toString());
        }

        // Show dialog.
        this.sandbox.emit(
            'sulu.overlay.show-warning',
            'sulu.overlay.be-careful',
            warningMessage,
            callbackFunction.bind(this, false),
            callbackFunction.bind(this, true)
        )
        ;
    };

    return {
        /**
         * Shows a dialog when a contact should be deleted.
         *
         * @param {Object} sandbox
         * @param {Object} product
         */
        show: function(sandbox, product) {
            if (!!sandbox && !!product) {
                this.sandbox = sandbox;
                confirmDeleteDialog.call(
                    this,
                    function(wasConfirmed) {
                        if (wasConfirmed) {
                            this.sandbox.emit('sulu.header.toolbar.item.loading', 'options-button');
                            product.destroy({
                                success: function() {
                                    this.sandbox.emit('sulu.router.navigate', 'pim/products');
                                }.bind(this)
                            });
                        }
                    }.bind(this),
                    product.attributes.numberOfVariants
                );
            }
        },

        /**
         * Shows a dialog when removing media from a product.
         *
         * @param {Object} sandbox
         * @param {Function} callback
         */
        showMediaRemoveDialog: function(sandbox, callback) {
            this.sandbox = sandbox;
            confirmDeleteDialog.call(this, callback);
        }
    };
});
