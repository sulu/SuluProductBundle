/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define(['config', 'suluproduct/util/product-delete-dialog'], function(Config, DeleteDialog) {

    'use strict';

    var constants = {
        instanceName: 'documents',
        formSelector: '#documents-form',

        fieldsKey: 'productMedia',
        fieldsUrl: 'api/products/media/fields'
    };

    return {

        view: true,
        templates: ['/admin/product/template/product/documents'],

        initialize: function() {
            this.newSelectionItems = [];
            this.newSelections = [];
            this.removedSelections = [];
            this.currentSelection = this.sandbox.util.arrayGetColumn(this.options.data.attributes.media, 'id');

            if (!!this.options.data) {
                this.status = this.options.data.attributes.status;
            } else {
                this.status = Config.get('product.status.inactive');
            }

            // reset status if it has been changed before and has not been saved
            this.sandbox.emit('product.state.change', this.status);
            this.setHeaderBar(true);
            this.render();
            this.bindCustomEvents();
        },

        render: function() {
            this.html(this.renderTemplate(this.templates[0]));
            this.startSelectionOverlay();
            this.initList();
        },

        bindCustomEvents: function() {
            this.sandbox.on('sulu.toolbar.delete', function() {
                this.sandbox.emit('sulu.product.delete', this.options.id);
            }.bind(this));

            this.sandbox.on('product.state.change', function(status) {
                if (!this.options.data ||
                    !this.options.data.attributes.status ||
                    this.options.data.attributes.status.id !== status.id
                ) {
                    this.status = status;
                    this.options.data.attributes.status = this.status;
                    this.setHeaderBar(false);
                }
            }, this);

            this.sandbox.on('sulu.toolbar.save', this.submit.bind(this));

            this.sandbox.on('sulu.header.back', function() {
                this.sandbox.emit('sulu.products.list');
            }, this);

            this.sandbox.on('sulu.products.saved', this.savedProduct.bind(this));

            // checkbox clicked
            this.sandbox.on('husky.datagrid.' + constants.instanceName + '.number.selections', function(number) {
                var postfix = number > 0 ? 'enable' : 'disable';
                this.sandbox.emit('husky.toolbar.documents.item.' + postfix, 'deleteSelected', false);
            }, this);

            this.sandbox.on('sulu.products.media.removed', this.removeItemsFromList.bind(this));

            this.sandbox.on('sulu.products.media.saved', this.addItemsToList.bind(this));

            this.sandbox.on('husky.overlay.' + constants.instanceName + '.closed', this.submitMedia.bind(this));

            this.sandbox.on(
                'sulu.media-selection-overlay.' + constants.instanceName + '.record-selected',
                this.selectItem.bind(this)
            );
            this.sandbox.on(
                'sulu.media-selection-overlay.' + constants.instanceName + '.record-deselected',
                this.deselectItem.bind(this)
            );
        },

        /**
         * Removes elements from list
         */
        removeItemsFromList: function() {
            var ids = this.removedSelections.slice();
            ids.forEach(function(id) {
                this.sandbox.emit('husky.datagrid.' + constants.instanceName + '.record.remove', id);
            }.bind(this));
            this.setHeaderBar(true);
            this.removedSelections = [];
        },

        /**
         * Adds new elements to the list
         */
        addItemsToList: function() {
            this.newSelectionItems.forEach(function(item) {
                this.sandbox.emit('husky.datagrid.' + constants.instanceName + '.record.add', item);
            }.bind(this));
            this.setHeaderBar(true);
            this.newSelectionItems = [];
            this.newSelections = [];
        },

        savedProduct: function() {
            this.options.data.attributes.status = this.status;
            this.setHeaderBar(true);
        },

        deselectItem: function(id) {
            if (!!this.ignoreUpdates) {
                return;
            }

            var selectionIndex = this.currentSelection.indexOf(id);

            if (this.newSelections.indexOf(id) > -1) {
                var index = this.newSelections.indexOf(id);
                this.newSelections.splice(index, 1);
                this.newSelectionItems.splice(index, 1);
            // when an element is in current selection and was deselected
            } else if (selectionIndex > -1 && this.removedSelections.indexOf(id) === -1) {
                this.removedSelections.push(id);
            }

            if (selectionIndex > -1) {
                this.currentSelection.splice(selectionIndex, 1);
            }
        },

        selectItem: function(id, item) {
            if (!!this.ignoreUpdates) {
                return;
            }

            if (this.removedSelections.indexOf(id) > -1) {
                this.removedSelections.splice(this.removedSelections.indexOf(id), 1);
                this.currentSelection.push(id);
            // add element when it is really new and not already selected
            } else if (this.currentSelection.indexOf(id) < 0 && this.newSelections.indexOf(id) < 0) {
                this.newSelections.push(id);
                this.newSelectionItems.push(item);
                this.currentSelection.push(id);
            }
        },

        /**
         * Updates selected items in overlay
         */
        updateOverlaySelected: function() {
            this.ignoreUpdates = true;
            this.sandbox.emit('sulu.media-selection-overlay.documents.set-selected', this.currentSelection);
            this.ignoreUpdates = false;
        },

        /**
         * Saves / removes media from the product.
         */
        submitMedia: function() {
            if (this.newSelections.length > 0 || this.removedSelections.length > 0) {
                this.sandbox.emit(
                    'sulu.products.media.save',
                    this.options.data.id,
                    this.newSelections,
                    this.removedSelections
                );

                this.saved = false;
            }
        },

        /**
         * Saves product when status is changed.
         */
        submit: function() {
            this.options.data.attributes.status = this.status;
            this.sandbox.emit('sulu.products.save', this.options.data.attributes, true);
            this.saved = false;
        },

        // @var Bool saved - defines if saved state should be shown
        setHeaderBar: function(saved) {
            if (saved !== this.saved) {
                if (!!saved) {
                    this.sandbox.emit('sulu.header.toolbar.item.disable', 'save', true);
                } else {
                    this.sandbox.emit('sulu.header.toolbar.item.enable', 'save', false);
                }
            }
            this.saved = saved;
        },

        /**
         * Opens
         */
        showAddOverlay: function() {
            this.updateOverlaySelected()
            this.sandbox.emit('sulu.media-selection-overlay.documents.open');
        },

        /**
         * Removes all selected items
         */
        removeSelected: function() {
            this.sandbox.emit('husky.datagrid.documents.items.get-selected', function(ids) {
                DeleteDialog.showMediaRemoveDialog(this.sandbox, function(wasConfirmed) {
                    if (wasConfirmed) {
                        this.currentSelection = this.sandbox.util.removeFromArray(this.currentSelection, ids);
                        this.removedSelections = ids;
                        this.submitMedia();
                    }
                }.bind(this));
            }.bind(this));
        },

        /**
         * Initializes the datagrid-list
         */
        initList: function() {
            this.sandbox.sulu.initListToolbarAndList.call(this, constants.fieldsKey, constants.fieldsUrl,
                {
                    el: this.$find('#list-toolbar-container'),
                    instanceName: constants.instanceName,
                    template: this.getListTemplate(),
                    hasSearch: true
                },
                {
                    el: this.$find('#documents-list'),
                    url: this.getListUrl(),
                    searchInstanceName: constants.instanceName,
                    instanceName: constants.instanceName,
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
         * Returns the url for the list for a specific product
         *
         * @returns {string}
         */
        getListUrl: function() {
            return 'api/products/' + this.options.data.id + '/media?flat=true'
        },

        /**
         * @returns {Array} buttons used by the list-toolbar
         */
        getListTemplate: function() {
            return this.sandbox.sulu.buttons.get({
                add: {
                    options: {
                        callback: this.showAddOverlay.bind(this)
                    }
                },
                deleteSelected: {
                    options: {
                        callback: this.removeSelected.bind(this)
                    }
                }
            });
        },

        /**
         * Starts the overlay-component responsible for selecting the documents
         */
        startSelectionOverlay: function() {
            var $container = this.sandbox.dom.createElement('<div/>');
            this.sandbox.dom.append(this.$el, $container);

            this.sandbox.start([{
                name: 'media-selection-overlay@sulumedia',
                options: {
                    el: $container,
                    instanceName: constants.instanceName,
                    preselectedIds: this.currentSelection
                }
            }]);
        }
    };
});
