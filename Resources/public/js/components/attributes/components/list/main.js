/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define(['app-config'], function (AppConfig) {

    'use strict';

    // add ckicked
    var bindCustomEvents = function() {
            this.sandbox.on('sulu.list-toolbar.add', function() {
                this.sandbox.emit('sulu.product.attributes.new');
            }.bind(this));

            // delete clicked
            this.sandbox.on('sulu.list-toolbar.delete', function() {
                this.sandbox.emit('husky.datagrid.items.get-selected', function(ids) {
                    this.sandbox.emit('sulu.product.attributes.delete', ids);
                }.bind(this));
            }.bind(this));
        },

        datagridClicked = function(id) {
            this.sandbox.emit('sulu.product.attributes.load', id, AppConfig.getUser().locale);
        };

    return {

        view: true,

        layout: {
            content: {
                width: 'max'
            }
        },

        header: function () {
            return {
                title: 'pim.attributes.title',
                noBack: true,

                breadcrumb: [
                    {title: 'navigation.pim'},
                    {title: 'pim.attributes.title'}
                ]
            };
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
                    parentTemplate: 'default',
                    inHeader: true
                },
                {
                    el: this.sandbox.dom.find('#attributes-list', this.$el),
                    url: '/admin/api/attributes?flat=true',
                    resultKey: 'attributes',
                    searchInstanceName: 'attributes',
                    searchFields: ['name'],
                    actionCallback: datagridClicked.bind(this)
                }
            );
        },

        render: function () {
            this.renderGrid();
        }
    };
});
