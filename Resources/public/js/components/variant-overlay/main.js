/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * @class variant-overlay
 * @constructor
 * Overlay component that handles creation and editing of variants.
 *
 * @params {Object}   [options] Configuration object
 * @params {Array}    [options.currencies] Currencies that are available in product bundle.
 * @params {Object}   [options.data] Data to fill overlay with.
 * @params {String}   [options.instanceName = null] Name of this instance.
 * @params {String}   [options.locale] Default locale for overlay.
 * @params {Array}    [options.parentPrices] Prices of the parent product.
 * @params {Array}    [options.variantAttributes] List of attributes that have to be implemented set by each variant.
 * @params {Function} [options.okCallback] Callback function when variant has been saved. Provides data
 *                     and locale as parameter.
 */
define([
    'config',
    'suluproduct/models/product',
    'text!suluproduct/components/variant-overlay/details.html',
    'text!suluproduct/components/variant-overlay/prices.html',
    'text!suluproduct/components/variant-overlay/warning.html'
], function(Config, Product, DetailsTemplate, PricesTemplate, WarningTemplate) {

    'use strict';

    var defaults = {
            currencies: [],
            data: {},
            instanceName: null,
            locale: null,
            variantAttributes: [],
            parentPrices: []
        },

        selectors = {
            form: '#js-variant-form',
            overlayContent: '.variant-overlay-content'
        },

        namespace = 'sulu.product-variant-overlay.',

        /**
         * Raised when the overlay get closed
         *
         * @event sulu.product-variant-overlay.closed
         */
        CLOSED = function() {
            return createEventName.call(this, 'closed');
        },

        /**
         * Raised when component is initialized.
         *
         * @event sulu.product-variant-overlay.initialized
         */
        INITIALIZED = function() {
            return createEventName.call(this, 'initialized');
        },

        /**
         * Returns normalized event names.
         */
        createEventName = function(eventName) {
            return namespace + retrievePreparedInstanceName.call(this) + eventName;
        },

        /**
         * Returns instance name with a postfixed '.' if its defined.
         * Otherwise an empty string is returned.
         *
         * @returns {String}
         */
        retrievePreparedInstanceName = function() {
            var instanceName = '';
            if (typeof this.options.instanceName === 'string') {
                instanceName = instanceName + '.';
            }

            return instanceName;
        },

        /**
         * Bind custom events.
         */
        bindCustomEvents = function() {
            this.sandbox.on(retrieveOverlayEventName.call(this, 'language-changed'), onLanguageChanged.bind(this));
            this.sandbox.on(retrieveOverlayEventName.call(this, 'closed'), onCloseOverlay.bind(this));

        },

        /**
         * Destroy this component.
         */
        onCloseOverlay = function() {
            this.sandbox.stop(this.$el);
            this.sandbox.emit(CLOSED.call(this));
        },

        /**
         * Bind DOM events.
         */
        bindDomEvents = function() {
            this.sandbox.dom.on(this.$el, 'click', onChangePriceClicked.bind(this), '.change-price');
        },

        /**
         * Called when locale is changed.
         *
         * @param {String} locale
         */
        onLanguageChanged = function(locale) {
            this.changeLocale = locale;
            this.sandbox.emit(retrieveOverlayEventName.call(this, 'slide-right'));
        },

        /**
         * Called when warnings OK button is clicked.
         *
         * @returns {boolean}
         */
        onWarningOkClicked = function() {
            this.sandbox.emit(retrieveOverlayEventName.call(this, 'slide-left'));
            changeLanguage.call(this, this.changeLocale);

            return false;
        },

        /**
         * Called when warnings CANCEL button is clicked.
         *
         * @returns {boolean}
         */
        onWarningCancelClicked = function() {
            this.sandbox.emit(retrieveOverlayEventName.call(this, 'slide-left'));

            // Set back local to previous selection.
            var locales = this.sandbox.sulu.locales;
            this.sandbox.emit('husky.select.null.update', locales , [locales.indexOf(this.options.locale)], false);

            return false;
        },

        /**
         * Function triggeres language change.
         *
         * @param {String} locale
         */
        changeLanguage = function(locale) {
            this.options.locale = locale;
            loadDataAndSetForm.call(this);
        },

        /**
         * Updates corresponding input field when when change-price checkbox is clicked.
         *
         * @param {Object} event
         */
        onChangePriceClicked = function(event) {
            var currencyCode = $(event.target).data('currencyCode');
            var $input = $('#price-' + currencyCode);

            var isDisabled = false;
            $input.prop('disabled', function(index, value) {
                isDisabled = !value;

                return isDisabled;
            });

            // TODO: Add validation to empty field.
            if (isDisabled) {
                this.sandbox.form.updateConstraint(selectors.form, $input, 'required');
                $input.val('');
            } else {
                this.sandbox.form.addConstraint(selectors.form, $input, 'required', true);
                $input.focus();
            }
        },

        /**
         * Returns all overlay tabs and their template data.
         *
         * @returns {Array}
         */
        getOverlayTabs = function() {
            return [
                {
                    title: this.sandbox.translate('public.details'),
                    data: this.sandbox.dom.createElement(_.template(DetailsTemplate, {
                        translate: this.sandbox.translate
                    }))

                },
                {
                    title: this.sandbox.translate('content-navigation.product.pricing'),
                    data: this.sandbox.dom.createElement(_.template(PricesTemplate, {
                        translate: this.sandbox.translate
                    }))
                }
            ]
        },

        /**
         * Renders the variants list.
         */
        render = function() {
            var $overlay = this.sandbox.dom.createElement('<div id="js-variant-form"/>');
            this.sandbox.dom.append(this.$el, $overlay);

            var title = this.sandbox.translate('sulu_product.variant-overlay.title');
            if (this.options.data.id) {
                title = this.sandbox.translate('sulu_product.variant-overlay.title-edit');
            }

            // Create overlay.
            var overlay = this.sandbox.start([
                {
                    name: 'overlay@husky',
                    options: {
                        el: $overlay,
                        supportKeyInput: false,
                        title: title,
                        skin: 'normal',
                        openOnStart: true,
                        removeOnClose: true,
                        instanceName: this.options.instanceName,
                        slides: [
                            {
                                title: title,
                                tabs: getOverlayTabs.call(this),
                                languageChanger: {
                                    locales: this.sandbox.sulu.locales,
                                    preSelected: this.options.locale
                                },
                                propagateEvents: false,
                                okCallback: onOverlayOkClicked.bind(this)
                            },
                            {
                                title: this.sandbox.translate('sulu_product.variant-overlay.warning-title'),
                                data: this.sandbox.dom.createElement(_.template(WarningTemplate, {
                                    translate: this.sandbox.translate
                                })),
                                okCallback: onWarningOkClicked.bind(this),
                                cancelCallback: onWarningCancelClicked.bind(this)
                            }

                        ]
                    }
                }
            ]);

            this.sandbox.once(retrieveOverlayEventName.call(this, 'opened'), function() {
                loadDataAndSetForm.call(this);
                // Emit initialized event, once data is set.
                this.sandbox.emit(INITIALIZED.call(this));
            }.bind(this));
        },

        /**
         * Returns event name for husky overlay.
         *
         * @param {String} eventName
         *
         * @returns {String}
         */
        retrieveOverlayEventName = function(eventName) {
            return 'husky.overlay.' + retrievePreparedInstanceName.call(this) + eventName
        },

        /**
         * Fetches product based on options.data.id.
         * If non id given, the deferred is resolved immediately.
         *
         * @returns {Object} Deferred
         */
        fetchProductData = function() {
            var productDataLoaded = $.Deferred();
            if (this.options.data && this.options.data.id) {
                var product = Product.findOrCreate({id: this.options.data.id});
                product.fetchLocale(this.options.locale, {
                    success: function(data) {
                        productDataLoaded.resolve(data.toJSON());
                    }.bind(this),
                    error: function() {
                        productDataLoaded.reject();
                        console.error('Error while fetching product data');
                    }
                });
            } else {
                productDataLoaded.resolve();
            }

            return productDataLoaded.promise();
        },

        /**
         * Shows loader in overlay tabs and hides content.
         */
        showOverlayLoader = function() {
            $(selectors.overlayContent).addClass('is-hidden');
            this.sandbox.emit(retrieveOverlayEventName.call(this, 'show-loader'));
        },

        /**
         * Hides loader in overlay tabs and shows content.
         */
        hideOverlayLoader = function() {
            $(selectors.overlayContent).removeClass('is-hidden');
            this.sandbox.emit(retrieveOverlayEventName.call(this, 'hide-loader'));
        },

        /**
         * Sets up product data and initializes the form component.
         */
        loadDataAndSetForm = function() {
            // If there's data to load - show loader.
            if (this.options.data && this.options.data.id) {
                showOverlayLoader.call(this);
            }
            fetchProductData.call(this).then(function(product) {
                // Set product data. If non given set defaults.
                if (!product) {
                    product = {
                        attributes: retrieveParsedVariantAttributes.call(this)
                    }
                }
                // Parse prices of product.
                parseDataPrices.call(this, product);

                createOverlayForm.call(this, product);
            }.bind(this));
        },

        /**
         * Creates a data-mapper form and sets data.
         *
         * @param {Object} data
         */
        createOverlayForm = function(data) {
            // Initialize form.
            if (!this.form) {
                this.form = this.sandbox.form.create(selectors.form);
            }
            this.form.initialized.then(setOverlayData.bind(this, data));
        },

        /**
         * Sets data to overlay form.
         *
         * @param {Object} data
         */
        setOverlayData = function(data) {
            // Set data to form.
            this.sandbox.form.setData(selectors.form, data).then(function() {
                hideOverlayLoader.call(this);
            }.bind(this));
        },

        /**
         * Parses variant attributes into product-attribute like representation.
         */
        retrieveParsedVariantAttributes = function() {
            var attributes = [];
            this.sandbox.util.foreach(this.options.variantAttributes, function(variantAttribute) {
                attributes.push({
                    'attributeId': variantAttribute.id,
                    'attributeName': variantAttribute.name
                });
            });

            return attributes;
        },

        /**
         * Returns price from options.data by a given currency id, if found. Otherwise returns null.
         *
         * @param {Object} product
         * @param {Number} currencyId
         *
         * @returns {Object}
         */
        retrievePriceFromDataByCurrencyId = function(product, currencyId) {
            var price = null;
            if (product && product.prices && product.prices.length) {
                this.sandbox.util.foreach(product.prices, function(dataPrice) {
                    if (dataPrice.currency.id === currencyId) {
                        price = dataPrice;

                        // Break loop
                        return false;
                    }
                });
            }

            return price;
        },

        /**
         * Returns the parent price for a given currency id, if found.
         *
         * @param {Number} currencyId
         *
         * @returns {Object}
         */
        retrieveParentPriceForCurrencyId = function(currencyId) {
            if (this.options.parentPrices && this.options.parentPrices.length) {
                var price = null;
                this.sandbox.util.foreach(this.options.parentPrices, function(parentPrice) {
                    if (parentPrice.currency.id === currencyId && parseInt(parentPrice.minimumQuantity) === 0) {
                        price = parentPrice;

                        // Break loop
                        return false;
                    }
                }.bind(this));
            }

            return price;
        },

        /**
         * Returns base price string for a given currency.
         * If non given, returns '-'.
         *
         * @param {Number} currencyId
         *
         * @returns {string}
         */
        retrieveBasePriceStringForCurrency = function(currencyId) {
            var defaultPrice = '-';
            var parentPrice = retrieveParentPriceForCurrencyId.call(this, currencyId);
            if (parentPrice && parentPrice.hasOwnProperty('price')) {
                defaultPrice = this.sandbox.numberFormat(parentPrice.price, 'n2');
            }

            return defaultPrice;
        },

        /**
         * Parses prices for given object.
         *
         * @param {Object} product
         */
        parseDataPrices = function(product) {
            var prices = [];
            this.sandbox.util.foreach(this.options.currencies, function(currency) {
                var price = retrievePriceFromDataByCurrencyId.call(this, product, currency.id);

                if (!price) {
                    // Create an empty price object.
                    price = {
                        price: null,
                        currency: currency
                    }
                } else if (!price.hasOwnProperty('price')) {
                    price.price = null;
                }
                price.basePrice = retrieveBasePriceStringForCurrency.call(this, currency.id);

                prices.push(price);
            }.bind(this));

            product.prices = prices;
        },

        /**
         * Parses data received from data mapper to be valid for api.
         *
         * @param {Array} data
         */
        parseDataForApi = function(data) {
            // Parse attributes.
            this.sandbox.util.foreach(data.attributes, function(attribute) {
                attribute.attributeId = parseInt(attribute.attributeId);
            });

            // Parse prices.
            var prices = [];
            this.sandbox.util.foreach(data.prices, function(price) {
                // Do not save prices without a valid price.
                if (price.price == "") {
                    return;
                }
                price.price = parseInt(price.price);
                prices.push(price);
            });
            data.prices = prices;
        },

        /**
         * Callback when overlays OK button was clicked.
         * Triggers creation of a new variant.
         */
        onOverlayOkClicked = function() {
            if (!this.sandbox.form.validate(selectors.form)) {
                return false;
            }
            // Get data from form.
            var data = this.sandbox.form.getData(selectors.form);

            parseDataForApi.call(this, data);

            // Return as callback.
            if (typeof this.options.okCallback === 'function') {
                this.options.okCallback.call(this, data, this.options.locale)
            }
        };

    return {
        /**
         * Constructor of component.
         */
        initialize: function() {
            this.changeLocale = null;
            this.options = this.sandbox.util.extend(true, {}, defaults, this.options);

            // Render overlay.
            render.call(this);

            bindCustomEvents.call(this);
            bindDomEvents.call(this);
        }
    };
});
