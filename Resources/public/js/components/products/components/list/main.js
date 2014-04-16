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

    return {

        view: true,

        header: function() {
            return {
                title: 'pim.products.title',

                toolbar: {
                    template: []
                },

                breadcrumb: [
                    {title: 'navigation.pim'},
                    {title: 'pim.products.title'}
                ]
            };
        },

        templates: ['/admin/productbase/template/product/list'],

        initialize: function() {
            this.render();
        },

        render: function() {
            this.sandbox.dom.html(this.$el, this.renderTemplate('/admin/productbase/template/product/list'));

            // init list-toolbar and datagrid
            this.sandbox.sulu.initListToolbarAndList.call(this, 'productsFields', '/admin/productbase/api/productbases/fields',
                {
                    el: this.sandbox.dom.find('#list-toolbar-container', this.$el),
                    instanceName: 'productsToolbar',
                    inHeader: true,
                    template: [{
                            'id': 1,
                            'icon': 'user-add',
                            'title': 'Add User',
                            'class': 'highlight',
                            disabled: true
                        },
                        {
                            'id': 2,
                            'icon': 'bin',
                            'title': 'Delete User',
                            'disabled': true
                        },
                        {
                            'id': 'import',
                            'icon': 'file-import',
                            'title': 'Import',
                            'group': '2',
                            callback: function() {
                                this.sandbox.emit('sulu.pim.products.import');
                            }.bind(this)
                        },
                        {
                            'icon': 'file-export',
                            'title': 'Export',
                            disabled: true
                        }
                    ]
                },
                {
                    el: this.sandbox.dom.find('#products-list', this.$el),
                    url: '/admin/productbase/api/productbases?flat=true',
                    editable: false,
                    validation: false,
                    addRowTop: true,
                    progressRow: true,
                    paginationOptions: {
                        pageSize: 4
                    },
                    pagination: true,
                    selectItem: {
                        type: 'checkbox'
                    },
                    removeRow: false,
                    sortable: true
                }
            );
        }
    };
});
