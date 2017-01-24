/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define([
    'suluproduct/util/product-delete-dialog',
    'services/product/product-media-manager'
], function(DeleteDialog, ProductMediaManager) {

    'use strict';

    var constants = {
            instanceName: 'documents',
            datagridInstanceName: 'documents-list',
            formSelector: '#documents-form',

            fieldsKey: 'productMedia',
            fieldsUrl: 'api/products/media/fields'
        },

        /**
         * Returns the url for the list for a specific product.
         *
         * @returns {string}
         */
        getListUrl = function(productId, locale) {
            return 'api/products/' + productId + '/media?flat=true&locale=' + locale;
        },

        /**
         * Called when media has been saved.
         */
        onMediaUpdated = function() {
            // Update Datagrid.
            this.sandbox.emit('husky.datagrid.' + constants.datagridInstanceName + '.update');
            // Update save button.
            setSaveButton.call(this, this.saved, true);
            // Show success label.
            this.sandbox.emit('sulu.labels.success.show', 'labels.success.save-desc', 'labels.success');
        },

        /**
         * Called when product has been saved.
         */
        onProductSaved = function() {
            this.options.data.attributes.status = this.status;
            setSaveButton.call(this, true);
        },

        /**
         * Called when product status has changed.
         *
         * @param {Object} status
         */
        onProductStatusChanged = function(status) {
            if (this.options.data.attributes.status.id !== status.id) {
                this.status = status;
                this.options.data.attributes.status = this.status;
                setSaveButton.call(this, true);
            }
        },

        /**
         * This function enables or disables the toolbars delete button
         * when a datagrid element is selected.
         *
         * @param {Number} number
         */
        onDatagridCheckboxSelected = function(number) {
            var postfix = number > 0 ? 'enable' : 'disable';
            this.sandbox.emit(
                'husky.toolbar.' + constants.datagridInstanceName + '.item.' + postfix,
                'deleteSelected',
                false
            );
        },

        /**
         * Saves media to the product.
         *
         * @param {Object} data
         */
        submitMedia = function(data) {
            this.media = data;
            this.sandbox.emit('sulu.header.toolbar.item.loading', 'save');

            ProductMediaManager.save(
                this.options.data.id,
                {
                    mediaIds: this.sandbox.util.arrayGetColumn(data, 'id')
                }
            ).done(onMediaUpdated.bind(this));
        },

        /**
         * Shows the loader where save button is.
         */
        showLoader = function() {
            this.sandbox.emit('sulu.header.toolbar.item.loading', 'save');
        },

        /**
         * Performs patch for product status.
         */
        submitProduct = function() {
            this.options.data.attributes.status = this.status;
            this.sandbox.emit('sulu.products.save', this.options.data.attributes, true);
            this.saved = false;
        },

        /**
         * Sets status of header's save button.
         *
         * @param {Bool} disabled
         * @param {Bool} force Defines if status change should be forced.
         *                     May be needed, when button is in loading state.
         */
        setSaveButton = function(disabled, force) {
            if (force || disabled !== this.saved) {
                if (!!disabled) {
                    this.sandbox.emit('sulu.header.toolbar.item.disable', 'save', true);
                } else {
                    this.sandbox.emit('sulu.header.toolbar.item.enable', 'save', false);
                }
            }
            this.saved = disabled;
        },

        /**
         * Binds custom events to component.
         */
        bindCustomEvents = function() {
            this.sandbox.on('product.state.change', onProductStatusChanged.bind(this));

            this.sandbox.on('sulu.toolbar.save', submitProduct.bind(this));

            this.sandbox.on('sulu.header.back', function() {
                this.sandbox.emit('sulu.products.list');
            }, this);

            this.sandbox.on('sulu.products.saved', onProductSaved.bind(this));

            // Checkbox clicked.
            this.sandbox.on(
                'husky.datagrid.' + constants.datagridInstanceName + '.number.selections',
                onDatagridCheckboxSelected.bind(this)
            );
        },

        /**
         * Delete all product media with given ids.
         *
         * @param {Array} ids
         */
        deleteProductMedia = function(ids) {
            showLoader.call(this);

            var requests = [];
            _.each(ids, function(id) {
                requests.push(ProductMediaManager.delete(this.options.data.id, id));
            }.bind(this));

            // Disable loader once all requests are done.
            this.sandbox.dom.when.apply(null, requests).done(onMediaUpdated.bind(this));
        },

        /**
         * Removes all selected items.
         */
        onRemoveMediaClicked = function() {
            var ids = [];
            this.sandbox.emit(
                'husky.datagrid.' + constants.datagridInstanceName + '.items.get-selected',
                function(selectedIds) {
                    ids = selectedIds;
                }.bind(this)
            );

            DeleteDialog.showMediaRemoveDialog(this.sandbox, function(wasConfirmed) {
                if (wasConfirmed) {
                    // Remove selected ids from media.
                    this.media = this.media.filter(function(media) {
                        if (ids.indexOf(media.id) === -1) {
                            return media;
                        }
                    });

                    deleteProductMedia.call(this, ids);
                }
            }.bind(this));
        },

        /**
         * Opens media selection overlay.
         */
        showAddOverlay = function() {
            this.sandbox.emit(
                'sulu.media-selection-overlay.' + constants.instanceName + '.set-items',
                this.media
            );
            this.sandbox.emit('sulu.media-selection-overlay.' + constants.instanceName + '.open');
        },

        /**
         * Defines toolbar template.
         *
         * @returns {Array} Buttons used by the list-toolbar.
         */
        getToolbarTemplate = function() {
            return this.sandbox.sulu.buttons.get({
                edit: {
                    options: {
                        class: 'highlight',
                        callback: showAddOverlay.bind(this)
                    }
                },
                deleteSelected: {
                    options: {
                        callback: onRemoveMediaClicked.bind(this)
                    }
                }
            });
        };

    return {
        view: true,
        templates: ['/admin/product/template/product/documents'],

        /**
         * Initialization function of component.
         */
        initialize: function() {
            this.saved = false;
            this.media = this.options.data.attributes.media;
            this.status = this.options.data.attributes.status;

            // Reset status if it has been changed before and has not been saved.
            this.sandbox.emit('product.state.change', this.status);
            setSaveButton.call(this, true);

            this.render();

            bindCustomEvents.call(this);
        },

        /**
         * Renders UI of the component.
         */
        render: function() {
            this.html(this.renderTemplate(this.templates[0]));

            this.sandbox.once('husky.datagrid.' + constants.datagridInstanceName + '.loaded', function(mediaItems) {
                this.startSelectionOverlay(mediaItems._embedded.media);
            }.bind(this));
            this.initList();
        },

        /**
         * Initializes the datagrid-list.
         */
        initList: function() {
            this.sandbox.sulu.initListToolbarAndList.call(
                this,
                constants.fieldsKey,
                constants.fieldsUrl + '?locale=' + this.options.locale,
                {
                    el: this.$find('#list-toolbar-container'),
                    instanceName: constants.datagridInstanceName,
                    template: getToolbarTemplate.call(this),
                    hasSearch: true
                },
                {
                    el: this.$find('#documents-list'),
                    url: getListUrl(this.options.data.id, this.options.locale),
                    searchInstanceName: constants.datagridInstanceName,
                    instanceName: constants.datagridInstanceName,
                    resultKey: 'media',
                    searchFields: ['name', 'title', 'description'],
                    viewOptions: {
                        table: {
                            selectItem: {
                                type: 'checkbox'
                            }
                        }
                    }
                }
            );
        },

        /**
         * Starts the overlay-component responsible for selecting the documents.
         */
        startSelectionOverlay: function(preselected) {
            var $container = this.sandbox.dom.createElement('<div/>');
            this.sandbox.dom.append(this.$el, $container);

            this.sandbox.start([{
                name: 'media-selection/overlay@sulumedia',
                options: {
                    el: $container,
                    instanceName: constants.instanceName,
                    removeable: false,
                    preselected: preselected,
                    locale: this.options.locale,
                    saveCallback: submitMedia.bind(this)
                }
            }]);
        }
    };
});
