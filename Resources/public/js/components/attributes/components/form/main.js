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

    var formSelector = '#attribute-form',
        maxLengthTitle = 60;

    return {

        name: 'Sulu Attribute Form',

        view: true,

        templates: ['/admin/product/template/attribute/form'],

        header: function() {
            return {
                toolbar: {
                    template: 'default',
                    languageChanger: {
                        preSelected: this.options.locale
                    }
                }
            };
        },

        initialize: function() {
            this.saved = true;
            this.initializeValidation();
            this.bindCustomEvents();
            this.setHeaderBar(true);
            this.render();
            this.listenForChange();
        },

        bindCustomEvents: function() {
            this.sandbox.on('sulu.header.toolbar.save', function() {
                this.save();
            }.bind(this));

            this.sandbox.on('sulu.header.toolbar.delete', function() {
                this.sandbox.emit('sulu.product.attributes.delete', this.sandbox.dom.val('#id'));
            }.bind(this));

            this.sandbox.on('sulu.products.attributes.saved', function(id) {
                this.options.data.id = id;
                this.setHeaderBar(true);
                this.setHeaderInformation();
            }, this);

            // back to list
            this.sandbox.on('sulu.header.back', function() {
                this.sandbox.emit('sulu.product.attributes.list');
            }, this);

            this.sandbox.on('sulu.header.initialized', function() {
                this.setHeaderInformation();
            }, this);
        },

        initializeValidation: function() {
            this.sandbox.form.create(formSelector);
        },

        save: function() {
            if (this.sandbox.form.validate(formSelector)) {
                var data = this.sandbox.form.getData(formSelector);

                if (data.id === '') {
                    delete data.id;
                }

                data.type = {
                    id: 1
                };

                // TODO implement type handling
//                data.type = {
//                    id: types[this.options.productType]
//                };

                this.sandbox.emit('sulu.product.attributes.save', data);
            }
        },

        render: function() {
            this.sandbox.dom.html(this.$el, this.renderTemplate('/admin/product/template/attribute/form'));
            this.valuesList = this.sandbox.dom.find('#attribute-values-list', this.$el);
            this.valuesList.addClass('hidden');

            // datagrid
            var id = this.options.data.id,
                url = '/admin/api/attributes/' + id + '/values?flat=true';
            this.sandbox.start([{
                    name: 'datagrid@husky',
                    options: {
                        el: this.sandbox.dom.find('#attribute-values-list', this.$el),
                        url: url,
                        pagination: false,
                        instanceName: 'product-attributes',
                        resultKey: 'attributeValues',
                        view: 'table',
                        matchings: '/admin/api/attributes/values/fields',
                        contentFilters: {
                            selected: 'radio'
                        },
                        viewOptions: {
                            table: {
                                showHead: true,
                                editable: true,
                                validation: true,
                                fullWidth: false
                            },
                            selectItem: {
                                type: 'checkbox',
                                inFirstCell: true
                            }
                        }
                    }
                }
            ]);

            this.setHeaderInformation();

            this.initForm(this.options.data);
        },

        initForm: function(data) {
            // set form data
            var formObject = this.sandbox.form.create(formSelector);
            formObject.initialized.then(function() {
                this.setFormData(data);
            }.bind(this));
        },

        setFormData: function(data) {
            this.sandbox.form.setData(formSelector, data).then(function() {
                this.sandbox.start(formSelector);
            }.bind(this)).fail(function(error) {
                this.sandbox.logger.error("An error occured when setting data!", error);
            }.bind(this));
        },

        setHeaderInformation: function() {
            var title = 'pim.attribute.title',
                breadcrumb = [
                    {title: 'navigation.pim'},
                    {title: 'pim.attribute.title'}
                ];
            if (!!this.options.data && !!this.options.data.name) {
                title = this.options.data.name;
            }
            title = this.sandbox.util.cropTail(title, maxLengthTitle);
            this.sandbox.emit('sulu.header.set-title', title);

            if (!!this.options.data && !!this.options.data.id) {
                breadcrumb.push({
                    title: '#' + this.options.data.id
                });
            }
            this.sandbox.emit('sulu.header.set-breadcrumb', breadcrumb);
        },

        // @var Bool saved - defines if saved state should be shown
        setHeaderBar: function(saved) {
            if (saved !== this.saved) {
                var type = (!!this.options.data && !!this.options.data.id) ? 'edit' : 'add';
                this.sandbox.emit('sulu.header.toolbar.state.change', type, saved, true);
                this.propagateState(saved);
            }
            this.saved = saved;
        },

        /**
         * Propagates the state of the content with an event
         *  sulu.content.saved when the content has been saved
         *  sulu.content.changed when the content has been changed
         */
        propagateState: function(saved) {
            if (!!saved) {
                this.sandbox.emit('sulu.content.saved');
            } else {
                this.sandbox.emit('sulu.content.changed');
            }
        },

        listenForChange: function() {
            this.sandbox.dom.on('#attribute-form', 'change', function() {
                this.setHeaderBar(false);
            }.bind(this), 'select');
            this.sandbox.dom.on('#attribute-form', 'keyup', function() {
                this.setHeaderBar(false);
            }.bind(this), 'input, textarea');
            this.sandbox.on('husky.select.attribute-types.selected.item', function(id) {
                this.toggleValuesList(id);
                this.setHeaderBar(false);
            }.bind(this));
        },

        toggleValuesList: function(id) {
            // Don't show the value list for type 1 & 2
            // TODO deactivated until full implementation
//            if (id > 2) {
//                this.valuesList.removeClass('hidden');
//            } else {
//                this.valuesList.addClass('hidden');
//            }
        }
    };
});
