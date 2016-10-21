/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define([
    'config',
    'text!suluproduct/components/products/components/attributes/overlay-content.html',
    'services/product-type-manager',
    'suluproduct/collections/attributes',
    'suluproduct/models/variantAttribute'
], function(Config, OverlayTpl, ProductTypeManager, Attributes, VariantAttribute) {

    'use strict';

    var selectedAttributeId = null,
        selectedAttributeName = null,
        actions = {
            ADD: 1,
            DELETE: 2,
            UPDATE: 3
        },

        constants = {
            typeText: 'product.attribute.type.text',
            datagridInstanceName: 'product-attribute-datagrid',
            variantAttributesDatagridInstanceName: 'variant-attribute-datagrid',
            attributesToolbarInstanceName: 'product-attribute-list-toolbar',
            variantAttributesToolbarInstanceName: 'variant-attribute-list-toolbar',
            overlayInstanceName: 'product-attribute-overlay',
            selectInstanceName: 'product-attribute-select'
        },


        selectors = {
            variantAttributesContainer: '#js-variant-attributes-container'
        },

        /**
         * Fetches all attributes from database and parses them for beeing displayed in a select.
         *
         * @returns {Object} Deferred promise
         */
        fetchAttributesForSelect = function() {
            var deferred = $.Deferred();
            var attributes = new Attributes({locale: this.options.locale});
            attributes.fetch({
                success: function(data) {
                    var selectAttributes = [];
                    $.each(data.toJSON(), function(key, value) {
                        var attribute = {
                            'id': value.id,
                            'name': value.name
                        };

                        // At this time we support only text type attributes.
                        if (value.type.name === constants.typeText) {
                            selectAttributes.push(attribute);
                        }
                    }.bind(this));

                    deferred.resolve(selectAttributes);
                }.bind(this),
                error: function() {
                    console.error('Error retrieving attributes from server');
                    deferred.fail();
                }
            });

            return deferred.promise();
        },

        /**
         * Bind custom events.
         */
        bindCustomEvents = function() {
            this.sandbox.on('sulu.toolbar.delete', onDeleteClicked.bind(this));

            this.sandbox.on('product.state.change', onStatusChanged.bind(this));

            this.sandbox.on('sulu.toolbar.save', onSaveClicked.bind(this));

            this.sandbox.on('sulu.products.saved', onProductSaved.bind(this));

            this.sandbox.on(
                'husky.datagrid.' + constants.datagridInstanceName + '.number.selections',
                onProductAttributeSelection.bind(this, constants.attributesToolbarInstanceName)
            );

            this.sandbox.on(
                'husky.datagrid.' + constants.variantAttributesDatagridInstanceName + '.number.selections',
                onProductAttributeSelection.bind(this, constants.variantAttributesToolbarInstanceName)
            );
        },

        /**
         * Called when delete button is clicked.
         */
        onDeleteClicked = function() {
            this.sandbox.emit('sulu.product.delete', this.options.data.id);
        },

        /**
         * Enables or disables toolbar based on number of items that were selected.
         *
         * @param {Number} number
         * @param {String} toolBarInstanceName
         */
        onProductAttributeSelection = function(toolBarInstanceName, number) {
            var postfix = number > 0 ? 'enable' : 'disable';
            this.sandbox.emit(
                'husky.toolbar.' + toolBarInstanceName + '.item.' + postfix,
                'delete',
                false)
        },

        /**
         * Callback, when product has been saved.
         *
         * @param {Object} data
         */
        onProductSaved = function(data) {
            var attributes = data.attributes;

            // Select action.
            if (data.action === actions.ADD) {
                // Add records in datagrid.
                var attribute = _.findWhere(attributes, {'attributeId': data.attributeIdAdded});
                this.sandbox.emit('husky.datagrid.' + constants.datagridInstanceName + '.record.add', attribute);
            } else if (data.action === actions.DELETE) {
                // Delete records in datagrid.
                $.each(data.attributeIdsDeleted, function(key, id) {
                    this.sandbox.emit('husky.datagrid.' + constants.datagridInstanceName + '.record.remove', id);
                }.bind(this));
            } else if (data.action === actions.UPDATE) {
                // Update datagrid with received records.
                this.sandbox.emit('husky.datagrid.' + constants.datagridInstanceName + '.records.set', attributes);
            }

            setHeaderBar.call(this, true);
            this.options.data.attributes.status = this.status;
        },

        /**
         * Called when save button was clicked.
         */
        onSaveClicked = function() {
            this.sendData = {};
            this.sendData.status = this.status;
            this.sendData.id = this.options.data.id;
            save.call(this);
        },

        /**
         * Called when product status has changed.
         *
         * @param {Object} status
         */
        onStatusChanged = function(status) {
            if (!this.options.data
                || !this.options.data.attributes.status
                || this.options.data.attributes.status.id !== status.id
            ) {
                this.status = status;
                this.options.data.attributes.status = this.status;
                setHeaderBar.call(this, false);
            }
        },

        /**
         * @param {Boolean} saved Defines if saved state should be shown.
         */
        setHeaderBar = function(saved) {
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
         * Create overlay content for add attribute overlay.
         *
         * @param {Boolean} shouldHideValueField
         */
        createAddOverlayContent = function(shouldHideValueField) {
            selectedAttributeId = null;

            // Create container for overlay.
            var $overlayContent = this.sandbox.dom.createElement(this.sandbox.util.template(OverlayTpl, {
                translate: this.sandbox.translate,
                shouldHideValueField: shouldHideValueField
            }));
            this.sandbox.dom.append(this.$el, $overlayContent);

            return $overlayContent;
        },

        /**
         * Creates the overlay.
         */
        onAddAttributeClicked = function() {
            fetchAttributesForSelect.call(this).done(function(selectData) {
                createAddOverlay.call(
                    this,
                    createAddOverlayContent.call(this, false),
                    onAddAttributeOkClicked.bind(this)
                );
                startAttributesSelect.call(this, selectData);
            }.bind(this));
        },

        /**
         * Save product attributes.
         */
        save = function() {
            this.saved = false;
            this.sandbox.emit('sulu.products.save', this.sendData);
        },

        /**
         * Called when attribute and value have been selected in overlay.
         */
        onAddAttributeOkClicked = function() {
            // Exit if no attribute is selected in overlay.
            if (!selectedAttributeId) {
                return;
            }

            this.sendData = {};
            var attributeValueName = this.sandbox.dom.val('#attribute-name');

            var attributes = this.options.data.attributes.attributes;

            var result = _.findWhere(attributes, {'attributeId': selectedAttributeId});

            if (result) {
                result.attributeValueName = attributeValueName;
                result.attributeValueLocale = this.options.locale;
                this.sendData.action = actions.UPDATE;
            } else {
                var newAttribute = {
                    'attributeId': selectedAttributeId,
                    'attributeValueName': attributeValueName,
                    'attributeValueLocale': this.options.locale
                };
                attributes.push(newAttribute);
                this.sendData.action = actions.ADD;
            }

            this.sendData.attributeIdAdded = selectedAttributeId;
            this.sendData.attributes = attributes;
            this.sendData.status = this.status;
            this.sendData.id = this.options.data.attributes.id;

            save.call(this);
        },

        /**
         * Called when attribute has been selected in add variant attribute overlay.
         */
        onAddVariantAttributeOkClicked = function() {
            // Exit if no attribute is selected in overlay.
            if (!selectedAttributeId || !selectedAttributeName) {
                return;
            }

            var variantAttribute = new VariantAttribute({
                productId: this.options.data.id,
                attributeId: selectedAttributeId
            });
            variantAttribute.save(
                {},
                {
                    success: onAddVariantAttributeSuccess.bind(this),
                    error: onAddVariantAttributeError.bind(this)
                }
            );
        },

        /**
         * Called when delete variant attribute was clicked.
         */
        onDeleteVariantAttributeClicked = function() {
            var selectedIds = [];
            this.sandbox.emit(
                'husky.datagrid.' + constants.variantAttributesDatagridInstanceName + '.items.get-selected',
                function(ids) {
                    selectedIds = ids;
                }
            );

            var numberOfDeletions = selectedIds.length;
            if (numberOfDeletions < 1) {
                return;
            }

            var successCounter = 0;
            this.sandbox.util.foreach(selectedIds, function(value) {
                var variantAttribute = new VariantAttribute({
                    productId: this.options.data.id,
                    id: value
                });

                variantAttribute.destroy({
                    success: function() {
                        successCounter++;
                        onDeleteVariantAttributeSuccess.call(this, value);
                        checkDeleteSuccess.call(this, successCounter, numberOfDeletions);
                    }.bind(this),
                    error: onDeleteVariantAttributeError.bind(this)
                });
            }.bind(this));
        },

        /**
         * Called when post variant attribute was successful.
         */
        onAddVariantAttributeSuccess = function() {
            // TODO: translation
            this.sandbox.emit('sulu.labels.success.show', 'labels.success.save-desc', 'labels.success');
            this.sandbox.emit(
                'husky.datagrid.' + constants.variantAttributesDatagridInstanceName + '.record.add',
                {
                    'id': selectedAttributeId,
                    'name': selectedAttributeName
                }
            );
        },

        /**
         * Called when post variant attribute has failed.
         */
        onAddVariantAttributeError = function() {
            this.sandbox.emit('sulu.labels.error.show', 'sulu_product.labels.save-error', 'labels.error');
        },

        /**
         * Removes deleted attribute from variants datagrid.
         *
         * @param {Number} id
         */
        onDeleteVariantAttributeSuccess = function(id) {
            // Remove record from datagrid.
            this.sandbox.emit(
                'husky.datagrid.' + constants.variantAttributesDatagridInstanceName + '.record.remove',
                id
            );
        },

        /**
         * Shows delete success label when current number of deletion matches expected count.
         *
         * @param {Number} currentCount
         * @param {Number} expectedCount
         */
        checkDeleteSuccess = function(currentCount, expectedCount) {
            if (currentCount !== expectedCount) {
                return;
            }

            this.sandbox.emit('sulu.labels.success.show', 'labels.success.delete-desc', 'labels.success');
        },

        /**
         * Called when delete request is not successful.
         */
        onDeleteVariantAttributeError = function() {
            this.sandbox.emit('sulu.labels.error.show', 'sulu_product.labels.delete-error', 'labels.error');
        },

        /**
         * Delete action function from toolbar.
         */
        attributeDelete = function() {
            this.sandbox.emit('husky.datagrid.' + constants.datagridInstanceName + '.items.get-selected', function(ids) {

                var attributes = this.options.data.attributes.attributes;
                this.sendData = {};
                var attributeIdsDeleted = [];

                _.each(ids, function(value) {
                    var result = _.findWhere(attributes, {'attributeId': value});
                    attributes = _.without(attributes, result);
                    attributeIdsDeleted.push(value);
                });

                this.sendData.attributeIdsDeleted = attributeIdsDeleted;
                this.sendData.attributes = attributes;
                this.sendData.status = this.status;
                this.sendData.id = this.options.data.id;
                this.sendData.action = actions.DELETE;

                save.call(this);
            }.bind(this));
        },

        /**
         * On badge attributeName for datagrid.
         *
         * @param {Object} item
         * @param {Object} badge
         * @param {String} locale
         */
        onBadgeAttributeName = function(item, badge, locale) {
            if (item.attributeLocale
                && item.attributeLocale == item.fallbackLocale
                && item.attributeLocale != locale
            ) {
                badge.title = item.attributeLocale;

                return badge;
            }

            return false;
        },

        /**
         * On badge attributeValueName for datagrid.
         *
         * @param {Object} item
         * @param {Object} badge
         * @param {String} locale
         */
        onBadgeAttributeValueName = function(item, badge, locale) {
            if (item.attributeValueLocale
                && item.attributeValueLocale == item.fallbackLocale
                && item.attributeValueLocale != locale
            ) {
                badge.title = item.attributeValueLocale;

                return badge;
            }

            return false;
        },

        /**
         * Calls basic form components.
         */
        startFormComponents = function() {
            var datagridOptions = {
                el: '#product-attribute-list',
                instanceName: constants.datagridInstanceName,
                idKey: 'attributeId',
                resultKey: 'attributes',
                matchings: [
                    {
                        name: 'attributeName',
                        content: this.sandbox.translate('product.attribute.name')
                    },
                    {
                        name: 'attributeValueName',
                        content: this.sandbox.translate('product.attribute.value')
                    }
                ],
                viewOptions: {
                    table: {
                        type: 'checkbox',
                        badges: [
                            {
                                column: 'attributeName',
                                callback: function(item, badge) {
                                    return onBadgeAttributeName(item, badge, this.options.locale);
                                }.bind(this)
                            },
                            {
                                column: 'attributeValueName',
                                callback: function(item, badge) {
                                    return onBadgeAttributeValueName(item, badge, this.options.locale);
                                }.bind(this)
                            }
                        ]
                    }
                },
                data: this.options.data.attributes
            };

            this.sandbox.start([
                // Start datagrid.
                {
                    name: 'datagrid@husky',
                    options: datagridOptions
                },
                {
                    name: 'toolbar@husky',
                    options: {
                        el: '#product-attribute-toolbar',
                        instanceName: constants.attributesToolbarInstanceName,
                        small: false,
                        buttons: [
                            {
                                id: 'add',
                                icon: 'plus-circle',
                                callback: onAddAttributeClicked.bind(this)
                            },
                            {
                                id: 'delete',
                                icon: 'trash-o',
                                disabled: true,
                                callback: attributeDelete.bind(this)
                            }
                        ]
                    }
                }
            ]);
        },

        /**
         * Starts and displays overlay component for adding variant attributes.
         */
        createAddOverlay = function(overlayContent, okCallback) {
            // Create container for overlay.
            var $overlay = this.sandbox.dom.createElement('<div>');
            this.sandbox.dom.append(this.$el, $overlay);

            // Create content.
            this.sandbox.start([
                {
                    name: 'overlay@husky',
                    options: {
                        el: $overlay,
                        supportKeyInput: false,
                        title: this.sandbox.translate('product.attribute.overlay.title'),
                        skin: 'normal',
                        openOnStart: true,
                        removeOnClose: true,
                        instanceName: constants.overlayInstanceName,
                        data: overlayContent,
                        okCallback: okCallback
                    }
                }
            ]);
        },

        /**
         * Starts the select component with given data.
         *
         * @param {Array} selectData
         */
        startAttributesSelect = function(selectData) {
            var preSelectedElement = [];
            var defaultLabel = this.sandbox.translate('product.attribute.overlay.defaultlabel');

            // Preselect first element.
            if (selectData.length > 0 &&
                typeof(selectData[0]) === "object" &&
                typeof(selectData[0].name) === "string"
            ) {
                selectedAttributeId = selectData[0].id;
                preSelectedElement.push(selectData[0].name);
            } else {
                defaultLabel = this.sandbox.translate('sulu_product.attribute.overlay.no-attributes')
            }

            // Create select box in overlay.
            var selectOptions = {
                el: '#selectBox',
                instanceName: constants.selectInstanceName,
                multipleSelect: false,
                defaultLabel: defaultLabel,
                preSelectedElements: preSelectedElement,
                valueName: 'name',
                isNative: true,
                data: selectData
            };

            this.sandbox.start([
                {
                    name: 'select@husky',
                    options: selectOptions
                }
            ]);

            // Define callback when attribute is selected.
            this.sandbox.on('husky.select.' + constants.selectInstanceName + '.selected.item', function(item, name) {
                selectedAttributeId = parseInt(item);
                selectedAttributeName = name;
            });
        },

        /**
         * Gets called when add button for adding an attribute was clicked.
         */
        onAddVariantAttributeClicked = function() {
            fetchAttributesForSelect.call(this).done(function(selectData) {
                // Get all Attributes that already have been added.
                var alreadyAddedAttributeIds = [];
                this.sandbox.emit(
                    'husky.datagrid.' + constants.variantAttributesDatagridInstanceName + '.records.get',
                    function(data) {
                        alreadyAddedAttributeIds = data.map(function(attribute) {
                            return attribute.id;
                        });
                    }.bind(this)
                );
                // Do not add attributes that already are added.
                var filteredSelectData = [];
                this.sandbox.util.foreach(selectData, function(data) {
                    if (alreadyAddedAttributeIds.indexOf(data.id) === -1) {
                        filteredSelectData.push(data);
                    }
                });

                createAddOverlay.call(
                    this,
                    createAddOverlayContent.call(this, true),
                    onAddVariantAttributeOkClicked.bind(this)
                );
                startAttributesSelect.call(this, filteredSelectData);
            }.bind(this));
        },

        /**
         * Starts toolbar and datagrid for managing variant attributes.
         */
        startVariantAttributeFormComponents = function() {
            var datagridOptions = {
                el: '#js-variant-attribute-list',
                instanceName: constants.variantAttributesDatagridInstanceName,
                resultKey: 'variantAttributes',
                matchings: '/admin/api/product-variant-attributes/fields?locale=' + this.options.locale,
                url: '/admin/api/products/' + this.options.data.id + '/variant-attributes?locale=' + this.options.locale
            };

            this.sandbox.start([
                // Start datagrid.
                {
                    name: 'datagrid@husky',
                    options: datagridOptions
                },

            ]);

            // Only show toolbar if no variants have been added yet.
            if (this.options.data.attributes.numberOfVariants === 0) {
                this.sandbox.start([
                    {
                        name: 'toolbar@husky',
                        options: {
                            el: '#js-variant-attribute-toolbar',
                            instanceName: constants.variantAttributesToolbarInstanceName,
                            small: false,
                            buttons: [
                                {
                                    id: 'add',
                                    icon: 'plus-circle',
                                    callback: onAddVariantAttributeClicked.bind(this)
                                },
                                {
                                    id: 'delete',
                                    icon: 'trash-o',
                                    disabled: true,
                                    callback: onDeleteVariantAttributeClicked.bind(this)
                                }
                            ]
                        }
                    }
                ]);
            }
        },

        /**
         * Initialize variant attributes components.
         */
        initVariantAttributesForm = function() {
            var productType = this.options.data.attributes.type.id;

            // If current product is a product with variants we also show product variants table.
            if (productType !== ProductTypeManager.types.PRODUCT_WITH_VARIANTS) {
                return;
            }

            // Show variant attributes container.
            $(selectors.variantAttributesContainer).removeClass('is-hidden');

            startVariantAttributeFormComponents.call(this);
        },

        /**
         * Initialize product attributes components.
         */
        initProductAttributesForm = function() {
            startFormComponents.call(this);
        };

    return {
        name: 'Sulu Product Attributes View',

        templates: ['/admin/product/template/product/attributes'],

        /**
         * Constructor of component.
         */
        initialize: function() {
            bindCustomEvents.call(this);

            // Set correct status.
            this.status = Config.get('product.status.inactive');
            if (!!this.options.data) {
                this.status = this.options.data.attributes.status;
            }
            // Reset status if it has been changed before and has not been saved.
            this.sandbox.emit('product.state.change', this.status);

            this.render();
            setHeaderBar.call(this, true);
        },

        /**
         * Renders component.
         */
        render: function() {
            this.sandbox.dom.html(
                this.$el,
                this.renderTemplate(
                    '/admin/product/template/product/attributes',
                    {
                        'translate': this.sandbox.translate
                    }
                )
            );

            initProductAttributesForm.call(this);
            initVariantAttributesForm.call(this);
        }
    };
});
