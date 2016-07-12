/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Product content type.
 *
 * Allows selection of multiple products.
 */
define(function() {

    'use strict';

    var defaults = {
            options: {
                eventNamespace: 'sulu.product-selection',
                resultKey: 'products',
                dataAttribute: 'product-selection',
                dataDefault: [],
                hidePositionElement: true,
                hideConfigButton: true,
                navigateEvent: 'sulu.router.navigate',
                translations: {
                    noContentSelected: 'product-selection.no-product-selected'
                }
            },

            templates: {
                data: [
                    '<div class="grid">',
                    '   <div class="grid-row search-row">',
                    '       <div class="grid-col-8"/>',
                    '       <div class="grid-col-4" id="product-selection-<%= instanceName %>-search"/>',
                    '   </div>',
                    '   <div class="grid-row">',
                    '       <div class="grid-col-12" id="product-selection-<%= instanceName %>-list"/>',
                    '   </div>',
                    '</div>'
                ].join(''),

                contentItem: [
                    '<a href="#" class="link" data-id="<%= id %>">',
                    '   <span class="value"><%= name %></span>',
                    '   <span class="value"><%= number %></span>',
                    '</a>'
                ].join('')
            },

            translations: {
                name: 'product.name',
                overlayTitle: 'product.content-type.headline',
                number: 'product.number',
                id: 'public.id'
            }
        },

        /**
         * Handles aura-events.
         */
        bindCustomEvents = function() {
            this.sandbox.on(
                'husky.overlay.product-selection.' + this.options.instanceName + '.add.initialized',
                initializeList.bind(this)
            );

            this.sandbox.on(
                'husky.overlay.product-selection.' + this.options.instanceName + '.add.opened',
                updateList.bind(this)
            );

            this.sandbox.dom.on(this.$el, 'click', function(e) {
                var id = this.sandbox.dom.data(e.currentTarget, 'id'),
                    route = 'pim/products/edit:' + id;

                this.sandbox.emit(this.options.navigateEvent, route);

                return false;
            }.bind(this), 'a.link');

            // adjust position of overlay after column-navigation has initialized
            this.sandbox.on('husky.datagrid.product.view.rendered', function() {
                this.sandbox.emit('husky.overlay.product-selection.' + this.options.instanceName + '.add.set-position');
            }.bind(this));
        },

        /**
         * Initializes list in the overlay
         */
        initializeList = function() {
            this.sandbox.start([
                {
                    name: 'search@husky',
                    options: {
                        appearance: 'white small',
                        instanceName: this.options.instanceName + '-product-search',
                        el: '#product-selection-' + this.options.instanceName + '-search'
                    }
                },
                {
                    name: 'datagrid@husky',
                    options: {
                        el: '#product-selection-' + this.options.instanceName + '-list',
                        instanceName: 'product',
                        url: this.options.url,
                        preselected: this.getData() || [],
                        resultKey: this.options.resultKey,
                        sortable: false,
                        columnOptionsInstanceName: '',
                        clickCallback: function(item) {
                            this.sandbox.emit('husky.datagrid.product.toggle.item', item);
                        }.bind(this),
                        selectedCounter: true,
                        searchInstanceName: this.options.instanceName + '-product-search',
                        searchFields: ['name'],
                        paginationOptions: {
                            dropdown: {
                                limit: 20
                            }
                        },
                        matchings: [
                            {
                                content: this.translations.id,
                                name: 'id',
                                disabled: true
                            },
                            {
                                content: this.translations.name,
                                name: 'name'
                            },
                            {
                                content: this.translations.number,
                                name: 'number'
                            }
                        ]
                    }
                }
            ]);
        },

        /**
         * Updates the datagrid when opening the overlay again
         */
        updateList = function() {
            var data = this.getData() || [];

            this.sandbox.emit('husky.datagrid.product.selected.update', data);
        },

        /**
         * Handle dom events
         */
        bindDomEvents = function() {
            this.sandbox.dom.on(this.$el, 'click', function() {
                return false;
            }.bind(this), '.search-icon');

            this.sandbox.dom.on(this.$el, 'keydown', function(e) {
                if (event.keyCode === 13) {
                    e.preventDefault();
                    e.stopPropagation();

                    return false;
                }
            }.bind(this), '.search-input');
        },

        /**
         * Starts the overlay component
         */
        startOverlay = function() {
            var $element = this.sandbox.dom.createElement('<div/>');
            this.sandbox.dom.append(this.$el, $element);

            this.sandbox.start([
                {
                    name: 'overlay@husky',
                    options: {
                        triggerEl: this.$addButton,
                        cssClass: 'product-content-overlay',
                        el: $element,
                        removeOnClose: false,
                        container: this.$el,
                        instanceName: 'product-selection.' + this.options.instanceName + '.add',
                        skin: 'wide',
                        okCallback: getAddOverlayData.bind(this),
                        title: this.translations.overlayTitle,
                        data: this.templates.data({instanceName: this.options.instanceName})
                    }
                }
            ]);
        },

        /**
         * Retrieve data from datagrid and keep sorting of ids.
         */
        getAddOverlayData = function() {
            var data = [],
                oldData = this.getData();

            this.sandbox.emit('husky.datagrid.product.items.get-selected', function(selected) {
                this.sandbox.util.foreach(selected, function(item) {
                    var index = oldData.indexOf(item);

                    if (index !== -1) {
                        data[index] = item;
                    } else {
                        data.push(item);
                    }
                }.bind(this));
            }.bind(this));

            var keys = Object.keys(data),
                result = [],
                i, len = keys.length;

            for (i = 0; i < len; i++) {
                result.push(data[keys[i]]);
            }

            this.setData(result);
        };

    return {

        defaults: defaults,

        type: 'itembox',

        initialize: function() {
            // sandbox event handling
            bindCustomEvents.call(this);

            this.render();

            // init overlays
            startOverlay.call(this);

            // handle dom events
            bindDomEvents.call(this);
        },

        getUrl: function(data) {
            var delimiter = (this.options.url.indexOf('?') === -1) ? '?' : '&';

            return [
                this.options.url, delimiter, this.options.idsParameter, '=', (data || []).join(',')
            ].join('');
        },

        getItemContent: function(item) {
            return this.templates.contentItem(item);
        },

        sortHandler: function(ids) {
            this.setData(ids, false);
        },

        removeHandler: function(id) {
            var data = this.getData();
            for (var i = -1, length = data.length; ++i < length;) {
                if (id === data[i]) {
                    data.splice(i, 1);
                    break;
                }
            }

            this.setData(data, false);
        }
    };
});
