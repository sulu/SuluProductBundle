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

        templates: ['/admin/productbase/template/product/list'],

        initialize: function() {
            this.render();
        },

        render: function() {
            var testData = {
                items: [
                    {id: 1, key: '677373774', name: 'Flachkopfwinkelschleifer WEF 9-125', manufacturer: 'Metabo', categories: 'asdf, jkdjkd, jdkkdjf', lastImported: '1900-21-12'},
                    {id: 2, key: '677373774', name: 'Flachkopfwinkelschleifer WEF 9-125', manufacturer: 'Metabo', categories: 'asdf, jkdjkd, jdkkdjf', lastImported: '1900-21-12'}
                ]
            };
            this.html(this.renderTemplate('/admin/productbase/template/product/list'));
            console.log(this.sandbox.dom.find('#products-list', this.$el));
            // init list-toolbar and datagrid
            /*this.sandbox.sulu.initListToolbarAndList.call(this, 'contactsFields', '/admin/api/contacts/fields',
                {
                    el: '#list-toolbar-container',
                    instanceName: 'products'
                },
                {
                    el: this.sandbox.dom.find('#products-list', this.$el),
                    data: testData,
                    selectItem: {
                        type: 'checkbox'
                    },
                    tableHead: [
                        {content: 'name', width: "60%"},
                        {content: 'artcle number', width: "20%"},
                        {content: 'last imported', width: "10%"}
                    ],
                    excludeFields: ['id', 'translations'],
                    removeRow: false,
                    searchInstanceName: 'products',
                    sortable: true
                }
            );*/
        }
    };
});
