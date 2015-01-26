/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

define(['suluproduct/models/attribute', 'app-config'], function(Attribute, AppConfig) {

    'use strict';

    var eventNamespace = 'sulu.product.attributes.',

        /**
         * @event sulu.product.attributes.new
         * @description Opens the form for a new attribute
         */
        ATTRIBUTE_NEW = eventNamespace + 'new',

        /**
         * @event sulu.product.attributes.delete
         * @description Opens the form for a new attribute
         */
        ATTRIBUTE_DELETE = eventNamespace + 'delete',

        /**
         * @event sulu.product.attribute.save
         * @description Saves a given attribute
         */
        ATTRIBUTE_SAVE = eventNamespace + 'save',

        /**
         * @event sulu.product.attributes.list
         * @description Shows the list for attributes
         */
        ATTRIBUTE_LIST = eventNamespace + 'list';

    return {

        initialize: function() {
            this.attribute = null;

            this.bindCustomEvents();
            if (this.options.display === 'list') {
                this.renderList();
            } else if (this.options.display === 'form') {
                this.renderForm();
            }
        },

        bindCustomEvents: function() {
            this.sandbox.on(ATTRIBUTE_NEW, function(type) {
                this.newAttribute();
            }.bind(this));

            this.sandbox.on(ATTRIBUTE_SAVE, function(data) {
                this.save(data);
            }.bind(this));

            this.sandbox.on(ATTRIBUTE_DELETE, function(data) {
                if(this.sandbox.util.typeOf(data) === 'array') {
                    this.deleteAttributes(data);
                } else {
                    this.deleteAttribute(data);
                }
            }.bind(this));

            this.sandbox.on('husky.datagrid.item.click', function(id) {
                this.load(id, AppConfig.getUser().locale);
            }.bind(this));

            this.sandbox.on(ATTRIBUTE_LIST, function() {
                this.sandbox.emit('sulu.router.navigate', 'pim/attributes');
            }.bind(this));

            this.sandbox.on('sulu.header.language-changed', function(locale) {
                this.load(this.options.id, locale);
            }, this);
        },

        save: function(data) {
            this.sandbox.emit('sulu.header.toolbar.item.loading', 'save-button');
            this.attribute.set(data);
            this.attribute.saveLocale(this.options.locale, {
                success: function(response) {
                    var model = response.toJSON();
                    if (!!data.id) {
                        this.sandbox.emit('sulu.products.attributes.saved', model);
                    } else {
                        this.sandbox.emit('sulu.content.saved');
                        this.load(model.id, this.options.locale);
                    }
                }.bind(this),
                error: function() {
                    this.sandbox.logger.log('error while saving attribute');
                }.bind(this)
            });
        },

        newAttribute: function() {
            this.sandbox.emit(
                'sulu.router.navigate',
                'pim/attributes/' + AppConfig.getUser().locale + '/add'
            );
        },

        deleteAttribute: function(id) {
            if (!id && id != 0) {
                // TODO: translations
                this.sandbox.emit('sulu.overlay.show-error', 'sulu.overlay.delete-no-items');
                return;
            }
            this.showDeleteConfirmation(id, function(wasConfirmed) {
                if (wasConfirmed) {
                    // TODO: show loading icon
                    var attribute = Attribute.findOrCreate({id: id});
                    attribute.destroy({
                        success: function() {
                            this.sandbox.emit(
                                'sulu.router.navigate',
                                    'pim/attributes'
                            );
                        }.bind(this)
                    });
                }
            }.bind(this));
        },

        deleteAttributes: function(ids) {
            if (ids.length < 1) {
                // TODO: translations
                this.sandbox.emit('sulu.overlay.show-error', 'sulu.overlay.delete-no-items');
                return;
            }
            this.showDeleteConfirmation(ids, function(wasConfirmed, removeAttributes) {
                if (wasConfirmed) {
                    // TODO: show loading icon
                    ids.forEach(function(id) {
                        var attribute = Attribute.findOrCreate({id: id});
                        attribute.destroy({
                            data: {removeAttributes: !!removeAttributes},
                            processData: true,

                            success: function() {
                                this.sandbox.emit('husky.datagrid.record.remove', id);
                            }.bind(this)
                        });
                    }.bind(this));
                }
            }.bind(this));
        },

        showDeleteConfirmation: function(ids, callbackFunction) {
            if (ids.length === 0) {
                return;
            } else {
                // show dialog
                this.sandbox.emit(
                        'sulu.overlay.show-warning',
                        'sulu.overlay.be-careful',
                        'product.attributes.delete.warning',
                        callbackFunction.bind(this, false),
                        callbackFunction
                        );
            }
        },

        load: function(id, localization) {
            this.sandbox.emit('sulu.router.navigate', 'pim/attributes/' + localization + '/edit:' + id + '/details');
        },

        renderForm: function() {
            this.attribute = new Attribute();

            var $form = this.sandbox.dom.createElement('<div id="attributes-form-container"/>'),
                component = {
                    name: 'attributes/components/form@suluproduct',
                    options: {
                        el: $form,
                        locale: this.options.locale,
                        data: this.attribute.defaults()
                    }
                };

            this.html($form);

            if (!!this.options.id) {
                this.attribute.set({id: this.options.id});
                this.attribute.fetchLocale(this.options.locale, {
                    success: function(model) {
                        component.options.data = model.toJSON();
                        this.sandbox.start([component]);
                    }.bind(this)
                });
            } else {
                this.sandbox.start([component]);
            }
        },

        /**
         * Creates the view for the flat attribute list
         */
        renderList: function() {
            var $list = this.sandbox.dom.createElement('<div id="attributes-list-container"/>');
            this.html($list);
            this.sandbox.start([
                {
                    name: 'attributes/components/list@suluproduct',
                     options: {
                         el: $list
                     }
                }
            ]);
        }
    };
});
