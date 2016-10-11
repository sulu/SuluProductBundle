CHANGELOG for Sulu Product Bundle
=================================

* dev-develop

    * BUGFIX      Fixed wrong path to get action schema.
    * FEATURE     Added validation schema for get products api.

* 0.15.0 (2016-09-26)

    * FEATURE     Added config parameter for default formatter locale.

* 0.14.0 (2016-09-20)

    * FEATURE     Added many-to-many releation `variantAttributes` between product and attributes.
    * FEATURE     Added github templates for issues and pull-requests.
    * FEATURE     Added style-ci configuration.
    * FEATURE     Implemented new product type 'Variant'.
    * ENHANCEMENT Removed product type translations from database.

* 0.13.1 (2016-09-21)

    * FEATURE     Added function that ProductValueAttribute translation can be retrieved by a given locale.

* 0.13.0 (2016-09-07)

    * ENHANCEMENT Renamed js variables.
    * FEATURE     Prepared for Sulu 1.3 support.
    * FEATURE     Added possibility to add a root key for product categories.
    * FEATURE     Moved price formatter service from pricing-bundle to product bundle to remove dependency

* 0.12.6 (2016-08-29)

    * ENHANCEMENT Added config param 'display_recurring_prices'
    * ENHANCEMENT Added method to get products by one or more categoryIds and tags
    * ENHANCEMENT Added method to get products by global trade item number (gtin)

* 0.12.5 (2016-08-22)

    * FEATURE Added calculation of recurring prices to price-calculation util in javascript utils.

* 0.12.4 (2016-07-22)

    * BUGFIX  Added attribute values to attribute test data.

* 0.12.3 (2016-07-19)

    * BUGFIX  Added workaround for ProductTestData to fix a dubious bug in doctrine.

* 0.12.2 (2016-07-15)

    * ENHANCEMENT Added attributeKey to attributes api
    * ENHANCEMENT Added addons to api output of products

* 0.12.1 (2016-07-13)

    * BUGFIX      Fixed FieldDescriptor for categories
    * ENHANCEMENT Added method to get products by tags
    * ENHANCEMENT Added new content type for products

* 0.12.0 (2016-07-11)

    * FEATURE   Added method to get products by categories
    * FEATURE   Added possibility to add Attribute fixtures by adding the file name to config.yml
    * FEATURE   Added key to attributes
    * BUGFIX    Fixed tests of special prices
    * BUGFIX    Fixed wrong translation key

* 0.11.0 (2016-07-06)

    * ENHANCEMENT Added methods for price calculations of addons
    * ENHANCEMENT Added flag for recurring price
    * ENHANCEMENT Added API for product addons
    * ENHANCEMENT Added GET-api for currencies
    * ENHANCEMENT Added UI for addons.

* 0.10.7 (2016-07-04)

    * BUGFIX Rendering prices tab with special prices in admin

* 0.10.6 (2016-06-23)

    * BUGFIX Fixed issue with tags when adding a new product
    * ENHANCEMENT Added tests for tags in products

* 0.10.5 (2016-06-23)

    * FEATURE Added tags to product
    * ENHANCEMENT Improved ProductTestData by adding product statuses
    
* 0.10.4 (2016-06-21)

    * ENHANCEMENT Improved ContactTestData by adding use of ContactRepositories create contact function
    * ENHANCEMENT Cleanups in ProductPriceManager

* 0.10.3 (2016-06-15)

    * ENHANCEMENT Removed not used attribute types from fixtures
    * BUGFIX Display fallback locale when adding a new attribute value to a product
    * BUGFIX Generating thumbnails
