/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define(['config', 'suluproduct/util/productUpdate'], function(Config, ProductUpdate) {

    'use strict';

    var constants = {
            maxLengthTitle: 60,
            formSelector: '#documents-form'
        },

        /**
         * Sets header title, breadcrumb, toolbar
         */
        setHeader = function() {
            var title = 'pim.product.title',
                breadcrumb = [
                    {title: 'navigation.pim'},
                    {title: 'pim.products.title'}
                ];

            if (!!this.options.data && !!this.options.data.name) {
                title = this.options.data.name;
            }

            title = this.sandbox.util.cropTail(title, constants.maxLengthTitle);

            if (!!this.options.data && !!this.options.data.number) {
                breadcrumb.push({
                    title: '#' + this.options.data.number
                });
            } else {
                breadcrumb.push({
                    title: 'pim.product.title'
                });
            }

            this.sandbox.emit('sulu.header.set-title', title);
            this.sandbox.emit('sulu.header.set-breadcrumb', breadcrumb);
            return {
                toolbar: {
                    template: 'default',
                    languageChanger: {
                        preSelected: this.options.locale
                    }
                },
                tabs: false
            };
        };

    return {

        view: true,
        templates: ['/admin/product/template/product/documents'],

        initialize: function() {
            this.sandbox.data.when(ProductUpdate.update(this.sandbox)).then(function(data) {
                this.options.data = data;
                this.newSelections = [];
                this.removedSelections = [];
                this.currentSelection = this.getPropertyFromArrayOfObject(this.options.data.media, 'id');
                this.status = !!this.options.data ? this.options.data.status : Config.get('product.status.active');
                this.statusChanged = false;

                setHeader.call(this);

                this.setHeaderBar(true);
                this.render();
            }.bind(this));
        },

        getPropertyFromArrayOfObject: function(data, propertyName) {
            if (this.sandbox.util.typeOf(data) === 'array' &&
                data.length > 0 &&
                this.sandbox.util.typeOf(data[0]) === 'object') {
                var values = [];
                this.sandbox.util.foreach(data, function(el) {
                    values.push(el[propertyName]);
                }.bind(this));
                return values;
            } else {
                return data;
            }
        },

        render: function() {
            this.html(this.renderTemplate(this.templates[0]));
            this.initForm(this.options.data);
            this.bindCustomEvents();
        },

        initForm: function(data) {
            var formObject = this.sandbox.form.create(constants.formSelector);
            formObject.initialized.then(function() {
                this.setForm(data);
            }.bind(this));
        },

        setForm: function(data) {
            this.sandbox.form.setData(constants.formSelector, data).fail(function(error) {
                this.sandbox.logger.error("An error occured when setting data!", error);
            }.bind(this));
        },

        bindCustomEvents: function() {
            this.sandbox.on('sulu.header.toolbar.delete', function () {
                this.sandbox.emit('sulu.products.delete', this.sandbox.dom.val('#id'));
            }.bind(this));

            this.sandbox.on('product.state.change', function(id){
                if(!this.options.data.status || this.options.data.status.id !== id){
                    this.status = {id: id};
                    this.statusChanged = true;
                    this.setHeaderBar(false);
                }
            },this);

            this.sandbox.on('sulu.header.toolbar.save', function() {
                this.submit();
            }, this);

            this.sandbox.on('sulu.header.back', function() {
                this.sandbox.emit('sulu.products.list');
            }, this);

            this.sandbox.on('sulu.media-selection.document-selection.data-changed', function() {
                this.setHeaderBar(false);
            }, this);

            this.sandbox.on('sulu.products.media.removed', this.resetAndRemoveFromCurrent.bind(this));
            this.sandbox.on('sulu.products.media.saved', this.resetAndAddToCurrent.bind(this));
            this.sandbox.on('sulu.media-selection.document-selection.record-selected', this.selectItem.bind(this));
            this.sandbox.on('husky.dropzone.media-selection-document-selection.files-added', this.addedItems.bind(this));
            this.sandbox.on('sulu.media-selection.document-selection.record-deselected', this.deselectItem.bind(this));
            this.sandbox.on('sulu.products.saved', this.savedProduct.bind(this));
        },

        savedProduct: function(data){
            this.options.data = data;
            this.status = this.options.data.status;
            this.setHeaderBar(true);
        },

        resetAndRemoveFromCurrent: function(data) {
            this.setHeaderBar(true);
            this.newSelections = [];
            this.removedSelections = [];
            this.sandbox.util.foreach(data, function(id) {
                if (this.currentSelection.indexOf(id) > -1) {
                    this.currentSelection.splice(this.currentSelection.indexOf(id), 1);
                }
            }.bind(this));

            this.setForm(this.currentSelection);
        },

        resetAndAddToCurrent: function(data) {
            this.setHeaderBar(true);
            this.newSelections = [];
            this.removedSelections = [];
            this.currentSelection = this.currentSelection.concat(data);
            this.setForm(this.currentSelection);
        },

        deselectItem: function(id) {
            // when an element is in current selection and was deselected
            if (this.currentSelection.indexOf(id) > -1 && this.removedSelections.indexOf(id) === -1) {
                this.removedSelections.push(id);
            }

            if (this.newSelections.indexOf(id) > -1) {
                this.newSelections.splice(this.newSelections.indexOf(id), 1);
            }
        },

        /**
         * Processes an array of items
         * @param items - array of items
         */
        addedItems: function(items) {
            this.sandbox.util.foreach(items, function(item) {
                if (!!item && !!item.id) {
                    this.selectItem(item.id);
                }
            }.bind(this));
        },

        selectItem: function(id) {
            // add element when it is really new and not already selected
            if (this.currentSelection.indexOf(id) < 0 && this.newSelections.indexOf(id) < 0) {
                this.newSelections.push(id);
            }

            if (this.removedSelections.indexOf(id) > -1) {
                this.removedSelections.splice(this.removedSelections.indexOf(id), 1);
            }
        },

        /**
         * Submits the selection depending on the type
         */
        submit: function() {
            if (this.sandbox.form.validate(constants.formSelector)) {

                this.sandbox.emit(
                    'sulu.products.media.save',
                    this.options.data.id,
                    this.newSelections,
                    this.removedSelections
                );

                if(!!this.statusChanged){
                    this.options.data.status = this.status;
                    this.sandbox.emit('sulu.products.save', this.options.data);
                }
            }
        },

        /** @var Bool saved - defines if saved state should be shown */
        setHeaderBar: function(saved) {
            if (saved !== this.saved) {
                var type = (!!this.options.data && !!this.options.data.id) ? 'edit' : 'add';
                this.sandbox.emit('sulu.header.toolbar.state.change', type, saved, true);
            }
            this.saved = saved;
        }
    };
});
