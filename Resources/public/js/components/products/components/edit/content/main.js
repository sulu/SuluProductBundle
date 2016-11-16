/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define([
    'services/product/product-content-manager',
    'services/product/product-manager',
    'suluproduct/util/header',
    'text!suluproduct/components/products/components/edit/content/content.html',
], function(ProductContentManager, ProductManager, HeaderUtil, ContentTemplate) {

    'use strict';

    var defaults = {
            data: {}
        },

        selectors = {
            form: '#content-form'
        },

        /**
         * Bind custom events.
         */
        bindCustomEvents = function() {
            this.sandbox.on('sulu.toolbar.save', onProductSaveClicked.bind(this));
        },

        /**
         * Triggered when toolbar save button was clicked.
         */
        onProductSaveClicked = function() {
            if (!this.sandbox.form.validate(selectors.form)) {
                return;
            }

            // Show loader in toolbar.
            this.sandbox.emit('sulu.header.toolbar.item.loading', 'save');

            // Get data of form.
            var data = this.sandbox.form.getData(selectors.form);

            // Save content.
            var contentSaved = ProductContentManager.save(this.options.data.id, this.options.locale, data);

            // Check if product status was changed and save.
            var statusSaved = true;
            var changedStatus = HeaderUtil.retrieveChangedStatus();
            if (!!changedStatus) {
                statusSaved = ProductManager.saveStatus(this.options.data.id, changedStatus);
            }

            this.sandbox.util.when(contentSaved, statusSaved).then(onProductSaved.bind(this));
        },

        /**
         * Triggered when content has been saved to product.
         *
         * @param {Array} whenData
         */
        onProductSaved = function(whenData) {
            // Disable save button.
            HeaderUtil.setSaveButton(false);
            this.sandbox.emit('sulu.labels.success.show', 'labels.success.save-desc', 'labels.success');
            // Set form with data of response of first deferred.
            setFormData.call(this, whenData[0]);
        },

        /**
         * Sets data to form.
         *
         * @param {Object} data
         *
         * @returns {Object} Promise
         */
        setFormData = function(data) {
            var deferred = $.Deferred();

            // Initialize form.
            if (!this.formObject) {
                this.formObject = this.sandbox.form.create(selectors.form);
            }

            // When form is initialized set data.
            this.formObject.initialized.then(function() {
                this.sandbox.form.setData(selectors.form, data).then(function() {
                    deferred.resolve();
                })
            }.bind(this));

            return deferred.promise();
        },

        /**
         * Starts form and listens for changes of form.
         *
         * @returns {Bool}
         */
        startForm = function() {
            this.sandbox.start(selectors.form);
            this.sandbox.dom.on(this.$el, 'keyup', HeaderUtil.setSaveButton.bind(this, true));

            return true;
        },

        /**
         * Called when data has been set to form.
         */
        onFormDataSet = function() {
            if (!this.isFormStarted) {
                this.isFormStarted = startForm.call(this);
            }
        },

        /**
         * Renders component ui.
         */
        render = function(contentData) {
            // Render template.
            this.sandbox.dom.html(this.$el, _.template(ContentTemplate, {
                'translate': this.sandbox.translate
            }));

            // Set data to form.
            setFormData.call(this, contentData).then(onFormDataSet.bind(this));
        };

    return {
        /**
         * Defines page layout.
         *
         * @return {Object}
         */
        layout: function() {
            return {
                extendExisting: true,

                content: {
                    width: 'fixed',
                    rightSpace: false,
                    leftSpace: false
                }
            };
        },

        /**
         * Initialization function of variants-list.
         */
        initialize: function() {
            this.isFormStarted = false;
            this.formObject = null;

            // Merge options with defaults.
            this.options = this.sandbox.util.extend(true, {}, defaults, this.options);
            this.status = this.options.data.attributes.status;

            // Set correct status in header bar.
            this.sandbox.emit('product.state.change', this.status);

            // Load contents then render component.
            ProductContentManager.load(this.options.data.id, this.options.locale).then(render.bind(this));

            bindCustomEvents.call(this);
        }
    };
});
