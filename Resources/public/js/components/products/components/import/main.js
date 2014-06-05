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

        templates: ['/admin/productbase/template/product/import'],

        initialize: function() {
            this.render();
            this.bindCustomEvents();
        },

        render: function() {
            this.html(this.renderTemplate('/admin/productbase/template/product/import'));
            this.sandbox.start([
                {
                    name: 'process@husky',
                    options: {
                        el: '#process',
                        instanceName: 'import',
                        data: [
                            {id: 1, name: 'Choose & upload file'},
                            {id: 2, name: 'Match header'},
                            {id: 3, name: 'Approve'},
                            {id: 4, name: 'Finish import'}
                        ],
                        activeProcess: 2
                    }
                },
                {
                    name: 'matcher@husky',
                    options: {
                        el: '#matcher',
                        instanceName: 'product-import',
                        data: [
                            {
                                id: 1,
                                title: 'Product number',
                                matched: false,
                                samples: ['1234', '5678'],
                                suggestion: {
                                    table: 'products',
                                    col: 'id',
                                    name: 'Products id'
                                }
                            },
                            {
                                id: 2,
                                title: 'Product title',
                                matched: false,
                                samples: ['Soup', 'Noodles'],
                                suggestion: {
                                    table: 'products',
                                    col: 'title',
                                    name: 'Products title'
                                }
                            },
                            {
                                id: 3,
                                title: 'Product description',
                                matched: false,
                                samples: ['hot', 'tasty']
                            }
                        ],
                        dbColumns: [
                            {
                                table: 'products',
                                col: 'id',
                                name: 'Products id'
                            },
                            {
                                table: 'products',
                                col: 'title',
                                name: 'Products title',
                                multiAssign: false
                            },
                            {
                                table: 'products',
                                col: 'description',
                                name: 'Products description',
                                multiAssign: true
                            },
                            {
                                table: 'products',
                                col: 'price',
                                name: 'Products price',
                                multiAssign: true
                            }
                        ]
                    }
                }
            ]);
        },

        /**
         * Binds custom related events
         */
        bindCustomEvents: function() {
            this.sandbox.on('husky.matcher.product-import.initialized', function() {
                this.sandbox.emit('husky.matcher.product-import.get-data', function(data) {
                    this.sandbox.dom.html(this.sandbox.dom.find('#unmatched-columns', this.$el), data.length);
                }.bind(this));
            }.bind(this));

            this.sandbox.on('husky.matcher.product-import.edited', function(unmatched) {
                this.sandbox.dom.html(this.sandbox.dom.find('#unmatched-columns', this.$el), unmatched);
            }.bind(this));
        }
    }
});
