/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define(function() {

    'use strict';

    // add ckicked
    var bindCustomEvents = function() {
            this.sandbox.on('sulu.toolbar.add', function() {
                this.sandbox.emit('sulu.product.attributes.new');
            }.bind(this));

            // delete clicked
            this.sandbox.on('sulu.toolbar.delete', function() {
                this.sandbox.emit('husky.datagrid.items.get-selected', function(ids) {
                    this.sandbox.emit('sulu.product.attributes.delete', ids);
                }.bind(this));
            }.bind(this));

            // checkbox clicked
            this.sandbox.on('husky.datagrid.number.selections', function(number) {
                var postfix = number > 0 ? 'enable' : 'disable';
                this.sandbox.emit('sulu.header.toolbar.item.' + postfix, 'deleteSelected', false);
            }, this);
        },

        datagridAction = function(id) {
            this.sandbox.emit('sulu.product.attributes.load', id);
        };

    return {

        view: true,

        layout: {
            content: {
                width: 'max'
            }
        },

        header: {
            noBack: true,
            title: 'pim.attributes.title',
            toolbar: {
                buttons: {
                    add: {},
                    deleteSelected: {}
                }
            }
        },

        templates: ['/admin/product/template/attribute/list'],

        initialize: function () {
            this.render();
            bindCustomEvents.call(this);
        },

        renderGrid: function () {
            this.sandbox.dom.html(this.$el, this.renderTemplate('/admin/product/template/attribute/list'));

            // init list-toolbar and datagrid
            this.sandbox.sulu.initListToolbarAndList.call(this, 'attributesFields', '/admin/api/attributes/fields',
                {
                    el: this.$find('#list-toolbar-container'),
                    instanceName: 'attributesToolbar',
                    template: this.sandbox.sulu.buttons.get({
                        settings: {
                            options: {
                                dropdownItems: [
                                    {
                                        type: 'columnOptions'
                                    }
                                ]
                            }
                        }
                    })
                },
                {
                    el: this.sandbox.dom.find('#attributes-list', this.$el),
                    url: '/admin/api/attributes?flat=true',
                    resultKey: 'attributes',
                    searchInstanceName: 'attributesToolbar',
                    searchFields: ['name'],
                    actionCallback: datagridAction.bind(this)
                }
            );
        },

        render: function () {
            this.renderGrid();
        }
    };
});
