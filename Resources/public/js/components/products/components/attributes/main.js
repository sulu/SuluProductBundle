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
        selected = 0,
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

            this.sandbox.on('product.state.change', function(id) {
                alert("OK3");
                /*
                if (!this.options.data.status || this.options.data.status.id !== id) {
                    this.status = {id: id};
                    this.options.data.status = this.status;
                    setHeaderBar.call(this, false);
                }
                */
            }, this);

            this.sandbox.on('sulu.products.saved', function(data) {
                alert("saved56");
                //setHeaderBar.call(this, true);
                //this.options.data = data;
                //this.options.data.status = this.status;
            }, this);

            app.sandbox.on('husky.select.attribute-types.selected.item', function(item) {
                console.log("drop-down multiple select ddms: selected item: " + item);
                selected = item;
            });

        },

        callbackFunc = function () {
            //alert("OK8");
            save.call(this);
        },

        save = function () {
            alert("ok9");
            //this.sandbox.emit('sulu.products.save', data);
        },

        /**
         * called when add icon was clicked
         */
        addButtonClicked = function() {
            // create overlay
            //createListOverlay.call(this);
            var attributeName = this.sandbox.dom.val('#attribute-name');
            var attributeSelection = this.sandbox.dom.val('.husky-select-label');
            alert(attributeSelection);
            app.sandbox.emit('husky.datagrid.' + datagridInstanceName + '.record.add', { value: attributeName }, callbackFunc());

            //alert("OK")
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
                        name: "id",
                        content: "id"
                    },
                    {
                        name: "value",
                        content: "value"
                    },
                    {
                        name: "attributeName",
                        content: "name"
                    },
                    {
                        name: "attributeTypeName",
                        content: "type"
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
        },

        render = function () {
            this.sandbox.dom.html(this.$el, this.renderTemplate('/admin/product/template/product/attributes'));

            initForm.call(this);

            //startFormComponents.call(this);

            //setHeaderInformation.call(this);

            //initForm.call(this, this.options.data);

        };

    return {
        name: 'Sulu Product Attributes View',

        templates: ['/admin/product/template/product/attributes'],

        initialize: function () {
            debugger;
            this.status  = !!this.options.data ? this.options.data.status : Config.get('product.status.active');



            //this.options = this.sandbox.util.extend({}, defaults, this.options);
            // merge translations
            //this.translations = this.sandbox.util.extend({}, translations, this.options.translations);

            this.options.isEditable = (this.options.isEditable === 'false' || this.options.isEditable === false) ? false : true;

            render.call(this);

            // event listener
           bindCustomEvents.call(this);

        }
    };
});
