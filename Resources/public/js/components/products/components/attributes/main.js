/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define([
    'config',
    'text!suluproduct/components/products/components/attributes/overlay-content.html'
], function(Config, OverlayTpl) {
    'use strict';

    var formSelector = '#product-attributes-form',
        productAttributesInstanceName = 'product-attribute-list-toolbar',
        datagridInstanceName = 'product-attribute-datagrid',
        overlayInstanceName = 'product-attribute-overlay',
        selectInstanceName = 'product-attribute-select',
        typeText = 'product.attribute.type.text',
        attributeId = null,
        maxLengthTitle = 60,
        actions = {
            ADD: 1,
            DELETE: 2,
            UPDATE: 3
        },

        /**
         * bind custom events
         */
        bindCustomEvents = function() {
            this.sandbox.on('sulu.header.toolbar.delete', function () {
                this.sandbox.emit('sulu.product.delete', this.options.data.id);
            }.bind(this));

            this.sandbox.on('product.state.change', function(id) {
                if (!this.options.data.status || this.options.data.status.id !== id) {
                    this.status = {id: id};
                    this.options.data.status = this.status;
                    setHeaderBar.call(this, false);
                }
            }, this);

            this.sandbox.on('sulu.header.back', function() {
                this.sandbox.emit('sulu.products.list');
            }, this);

            this.sandbox.on('sulu.header.toolbar.save', function() {
                this.sendData = new Object();
                this.sendData.status = this.status;
                save.call(this);
            }, this);

            this.sandbox.on('sulu.products.saved', function(data) {

                var attributes = data.attributes;

                // Select action
                if (data.action === actions.ADD) {
                    // ADD RECORD IN DATAGRID
                    var result = _.findWhere(attributes, {'attributeId': data.attributeId});
                    this.sandbox.emit('husky.datagrid.' + datagridInstanceName + '.record.add', {
                        id: result.id,
                        attributeId: result.attributeId,
                        value: result.value,
                        attributeName: result.attributeName
                    });
                } else if (data.action === actions.DELETE) {
                    // DELETE RECORDs IN DATAGRID
                    $.each(data.deleteIds, function(key, id) {
                        this.sandbox.emit('husky.datagrid.' + datagridInstanceName + '.record.remove', id);
                    }.bind(this));
                } else if (data.action === actions.UPDATE) {
                    // UPDATE RECORD IN DATAGRID
                    this.sandbox.emit('husky.datagrid.' + datagridInstanceName + '.records.change', attributes);
                }

                setHeaderBar.call(this, true);
                //this.options.data = data;
                this.options.data.attributes.status = this.status;
            }, this);
        },

        /**
         * modify header information
         */
        setHeaderBar = function(saved) {
            var type = (!!this.options.data && !!this.options.data.id) ? 'edit' : 'add';
            this.sandbox.emit('sulu.header.toolbar.state.change', type, saved, true);
        },

        /**
         * Create overlay content for add attribute overlay
         */
        createAddOverlayContent = function() {
            attributeId = null;

            // create container for overlay
            var $overlayContent = this.sandbox.dom.createElement(this.sandbox.util.template(OverlayTpl, {
                translate: this.sandbox.translate
            }));
            this.sandbox.dom.append(this.$el, $overlayContent);

            return $overlayContent;
        },

        /**
         * sets the header information
         */
        setHeaderInformation = function() {
            var title = 'pim.product.title',
                breadcrumb = [
                    {title: 'navigation.pim'},
                    {title: 'pim.products.title'}
                ];
            if (!!this.options.data && !!this.options.data.attributes.name) {
                title = this.options.data.attributes.name;
            }
            title = this.sandbox.util.cropTail(title, maxLengthTitle);
            this.sandbox.emit('sulu.header.set-title', title);

            if (!!this.options.data && !!this.options.data.attributes.number) {
                breadcrumb.push({
                    title: '#' + this.options.data.attributes.number
                });
            } else {
                breadcrumb.push({
                    title: 'pim.product.title'
                });
            }
            this.sandbox.emit('sulu.header.set-breadcrumb', breadcrumb);
        },

        /**
         * Create the overlay
         */
        createAddOverlay = function() {
            // call JSON to get the attributes from the server then create the overlay after it's done
            var ajaxRequest = $.getJSON('api/attributes', function(data) {
                this.attributeTypes = new Array();

                $.each(data._embedded.attributes, function(key, value) {
                    var newAttribute = {
                        'id': value.id,
                        'name': value.name
                    };

                    // at this time we support only text type attributes
                    if (value.type.name === typeText) {
                        this.attributeTypes.push(newAttribute);
                    }
                }.bind(this));
            }.bind(this));

            ajaxRequest.done(function() {
                // create container for overlay
                var $overlay = this.sandbox.dom.createElement('<div>');
                this.sandbox.dom.append(this.$el, $overlay);

                // create content
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
                            instanceName: overlayInstanceName,
                            data: createAddOverlayContent.call(this),
                            okCallback: overlayOkClicked.bind(this)
                        }
                    }
                ]);

            }.bind(this));

            ajaxRequest.fail(function() {
                console.log('Error retrieving attributes from server');
            }.bind(this));

            ajaxRequest.complete(function() {
                // create dropbox in overlay
                var selectOptions = {
                    el: '#selectBox',
                    instanceName: selectInstanceName,
                    multipleSelect: false,
                    defaultLabel: this.sandbox.translate('product.attribute.overlay.defaultlabel'),
                    valueName: 'name',
                    isNative: true,
                    data: this.attributeTypes
                };

                this.sandbox.start([
                    // start select
                    {
                        name: 'select@husky',
                        options: selectOptions
                    }
                ]);

                // define select event for dropbox
                this.sandbox.on('husky.select.' + selectInstanceName + '.selected.item', function(item) {
                    attributeId = parseInt(item);
                });
            }.bind(this));
        },

        /**
         * save product attributes
         */
        save = function() {
            this.sandbox.emit('sulu.products.save', this.sendData);
        },

        /**
         * called when OK on overlay was clicked
         */
        overlayOkClicked = function() {
            // exit if no attribute is selected in overlay
            if (!attributeId) {
                return;
            }

            this.sendData = new Object();
            var attributeValue = this.sandbox.dom.val('#attribute-name');

            var attributes = this.options.data.attributes.attributes;

            var result = _.findWhere(attributes, {'attributeId': attributeId});

            if (result) {
                result.value = attributeValue;
                this.sendData.action = actions.UPDATE;
            } else {
                var newAttribute = {
                    'attributeId': attributeId,
                    'value': attributeValue
                };
                attributes.push(newAttribute);
                this.sendData.action = actions.ADD;
            }

            this.sendData.attributeId = attributeId;
            this.sendData.attributes = attributes;
            this.sendData.status = this.status;
            this.sendData.id = this.options.data.attributes.id;

            save.call(this);
        },

        /**
         * delete action function from toolbar
         */
        attributeDelete = function() {
            this.sandbox.emit('husky.datagrid.' + datagridInstanceName + '.items.get-selected', function(ids) {

                var attributes = this.options.data.attributes.attributes;
                this.sendData = new Object();
                var deleteIds = new Array();

                _.each(ids, function(value, key, list) {
                    var result = _.findWhere(attributes, {'id': value});
                    attributes = _.without(attributes, result);
                    deleteIds.push(value);
                });

                this.sendData.deleteIds = deleteIds;
                this.sendData.attributes = attributes;
                this.sendData.status = this.status;
                this.sendData.id = this.options.data.id;
                this.sendData.action = actions.DELETE;

                save.call(this);
            }.bind(this));
        },

        /**
         * calls basic form components
         */
        startFormComponents = function() {
            var datagridOptions = {
                el: '#product-attribute-list',
                instanceName: datagridInstanceName,
                resultKey: 'attributes',
                matchings: [
                    {
                        name: 'value',
                        content: this.sandbox.translate('product.attribute.value')
                    },
                    {
                        name: 'attributeName',
                        content: this.sandbox.translate('product.attribute.name')
                    }
                ],
                viewOptions: {
                    table: {
                        type: 'checkbox'
                    }
                },
                data: this.options.data.attributes
            };

            this.sandbox.start([
                // start datagrid
                {
                    name: 'datagrid@husky',
                    options: datagridOptions
                }
            ]);

            this.sandbox.start([
                {
                    name: 'toolbar@husky',
                    options: {
                        el: '#product-attribute-selection-toolbar',
                        instanceName: productAttributesInstanceName,
                        small: false,
                        data: [
                            {
                                id: 'add',
                                icon: 'plus-circle',
                                callback: createAddOverlay.bind(this)
                            },
                            {
                                icon: 'trash-o',
                                disabled: false,
                                callback: attributeDelete.bind(this)
                            }
                        ]
                    }
                }
            ]);
        },

        /**
         * initialize form components
         */
        initForm = function() {
            this.sandbox.start(formSelector);
            startFormComponents.call(this);
        };

    return {
        name: 'Sulu Product Attributes View',

        templates: ['/admin/product/template/product/attributes'],

        render: function() {
            this.sandbox.dom.html(this.$el, this.renderTemplate('/admin/product/template/product/attributes'));

            initForm.call(this);

            setHeaderInformation.call(this);
        },

        initialize: function() {
            bindCustomEvents.call(this);

            this.status = !!this.options.data ? this.options.data.attributes.status : Config.get('product.status.active');

            this.render();
        }
    };
});
