/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define(['config'], function (Config) {
    'use strict';

    var formSelector = '#product-attributes-form',
        productAttributesInstanceName = 'product-attribute-list-toolbar',
        datagridInstanceName = 'product-attribute-datagrid',
        selected = 1,
        /*
        defaults = {
            data: [],
            isEditable: true,
            selectionUrl: null,
            url: null,
            resultKey: null,
            matchings: [
                {
                    name: 'id',
                    content: 'id',
                    disabled: true
                },
                {
                    name: 'value',
                    content: 'value'
                }
            ]
        },
        */

        /**
         * bind custom events
         */
        bindCustomEvents = function() {

            // resize overlay after datagrid is initialized
            this.sandbox.on('husky.datagrid.' + datagridInstanceName + '.data.save', function() {
                alert("OK11");
            }, this);

            this.sandbox.on('sulu.header.back', function () {
                this.sandbox.emit('sulu.products.list');
            }, this);

            app.sandbox.on('husky.select.attribute-types.selected.item', function(item) {
                selected = parseInt(item);
            });

        },

        save = function () {
            //this.sandbox.emit('sulu.products.save', this.options.data);
        },

        /**
         * called when add icon was clicked
         */
        addButtonClicked = function() {

            var attributeValue = this.sandbox.dom.val('#attribute-name');
            var attributeName = this.attributeTypes[selected-1].name;
            //app.sandbox.emit('husky.datagrid.' + datagridInstanceName + '.record.add', { id: selected, value: attributeValue, attributeName: attributeName }, callbackFunc());

            var attributes = this.options.data.attributes;

            var result = _.findWhere(attributes, { 'attributeId': selected });

            if (result) {
                result.attributeValue = attributeValue;
                console.log('attribute found');
                app.sandbox.emit('husky.datagrid.' + datagridInstanceName + '.records.change', { id: selected, value: attributeValue, attributeName: attributeName});

            } else {
                var new_attribute = {
                    "attributeId": selected,
                    "value": attributeValue
                };
                console.log('attribute not found');
                attributes.push(new_attribute);
                app.sandbox.emit('husky.datagrid.' + datagridInstanceName + '.record.add', { id: selected, value: attributeValue, attributeName: attributeName });
            }
            this.options.data.attributes = attributes;

            //this.sandbox.emit('husky.datagrid.records.change', this.options.data);
            //this.sandbox.emit('husky.datagrid.update');
        },

        /**
         * calls basic form components
         */
        startFormComponents = function() {

            var datagridOptions = {
                el: "#product-attribute-list",
                instanceName: datagridInstanceName,
                pagination: 'dropdown',
                resultKey: 'attributes',
                matchings: [
                    {
                        name: "value",
                        content: "value"
                    },
                    {
                        name: "attributeName",
                        content: "name"
                    }
                ],
                data: this.options.data
            };

            //this.sandbox.start('#product-attributes-form');

            /*
            var selectOptions = {
                el: '#selectBox',
                instanceName: selectInstanceName,
                multipleSelect: false,
                defaultLabel: 'Please choose attribute type',
                valueName: 'name',
                data: [
                {id: 0, name: 'Deutsch'},
                {id: 1, name: 'English'},
                {id: 2, name: 'Spanish'},
                {id: 3, name: 'Italienisch'}
                ]
            };

            this.sandbox.start([
                // start select
                {
                    name: 'select@husky',
                    options: selectOptions
                }

            ]);
            */
            this.sandbox.start([
                // start datagrid
                {
                    name: 'datagrid@husky',
                    options: datagridOptions
                }
            ]);

            if (!!this.options.isEditable) {
                // start toolbar
                this.sandbox.start([
                    {
                        name: 'toolbar@husky',
                        options: {
                            el: '#product-attribute-selection-toolbar',
                            instanceName: productAttributesInstanceName,
                            small: false,
                            data: [
                                {
                                    'id': 'add',
                                    'icon': 'plus-circle',
                                    'class': 'highlight-gray',
                                    'callback': addButtonClicked.bind(this)
                                }
                            ]
                        }
                    }
                ]);
            }
        },

        initForm = function() {
            var formObject = this.sandbox.form.create(formSelector);
            formObject.initialized.then(function() {
                this.sandbox.start(formSelector);
                startFormComponents.call(this);
            }.bind(this));
        };

    return {
        name: 'Sulu Product Attributes View',

        templates: ['/admin/product/template/product/attributes'],

        /**
         * is getting called when template is initialized
         * @param types
         */
        setTypes: function(types) {
            this.attributeTypes = types.attributeTypes;
        },

        render: function () {

            this.sandbox.once('sulu.products.set-types', this.setTypes.bind(this));

            this.sandbox.dom.html(this.$el, this.renderTemplate('/admin/product/template/product/attributes'));

            initForm.call(this);

            //startFormComponents.call(this);

            //setHeaderInformation.call(this);

            //initForm.call(this, this.options.data);

        },

        initialize: function () {
            debugger;
            this.status  = !!this.options.data ? this.options.data.status : Config.get('product.status.active');
            this.render();
            //this.options = this.sandbox.util.extend({}, defaults, this.options);
            // merge translations
            //this.translations = this.sandbox.util.extend({}, translations, this.options.translations);

            this.options.isEditable = (this.options.isEditable === 'false' || this.options.isEditable === false) ? false : true;

            bindCustomEvents.call(this);

        }
    };
});
