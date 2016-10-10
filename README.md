SuluProductBundle [![Build Status](https://travis-ci.org/sulu/SuluProductBundle.svg?branch=develop)](https://travis-ci.org/sulu/SuluProductBundle) [![StyleCI](https://styleci.io/repos/17173120/shield)](https://styleci.io/repos/17173120)
============================================================================================================================================================================================================================================

# Installation

Add the following to composer.json

```
"sulu/product-bundle": "~0.12"
```

Add the following to your application kernel:

```
// Product bundle
new Sulu\Bundle\ProductBundle\SuluProductBundle(),
new Sulu\Bundle\ValidationBundle\SuluValidationBundle(),
```

Add the following to your `admin/routing.yml`:

```
# Sulu Product Bundle
sulu_product:
    resource: "@SuluProductBundle/Resources/config/routing.yml"
    prefix: /admin/product

sulu_product_api:
    type: rest
    resource: "@SuluProductBundle/Resources/config/routing_api.yml"
    prefix: /admin/api
```

# Configuration

Sample configuration:

```
sulu_product:
    category_root_key: ~
    default_currency: 'EUR'
    default_formatter_locale 'en'
    display_recurring_prices: true
    fallback_locale: de
    fixtures:
        attributes:
            - src/Acme/Bundle/ProductBundle/DataFixtures/attributes.xml
    locales:
        - de
        - en
    objects:
        product:
            model: Acme\Bundle\ProductBundle\Entity\Product
            repository: Acme\Bundle\ProductBundle\Entity\ProductRepository
    template: AcmeShopBundle:views:templates/productdetail.html.twig
```

## Localization

Multiple locales for managing products can be defined in the config. 
If non defined, 'en' is taken as default.

If the users language matches any of the given locales, that one is displayed in the admin area.
Else the `fallback_locale` parameter is used.

## Recurring prices

The property recurring prices can be disabled via parameter `display_recurring_prices`.
This option hides the UI elements for recurring prices.

## Custom Entity and Repository

If you'd like to overwrite the sulu product entity you simply need to set the
`objects.product` parameters.

## Shop templates

For shop purposes `template` can be used to define the template for displaying
product details.

## Attribute Fixtures

You can write multiple attribute fixture files and define the path of your xmls in your config (as seen in the example above)

### Example XML

```xml
<attributes>
    <attribute>
        <key>example.key</key>
        <type>1</type>
        <names>
            <name locale="en">English Attribute</name>
            <name locale="de">German Attribute</name>
        </names>
        <values>
            <value>
                <name locale="de">German Value 1</name>
                <name locale="en">English Value 1</name>
            </value>
            <value>
                <name locale="de">German Value 2</name>
                <name locale="en">English Value 2</name>
            </value>
        </values>
    </attribute>
</attributes>

```

## Content type 'product'

```xml
<property name="product" type="product" mandatory="true">
    <meta>
        <title lang="de">Produkt</title>
        <title lang="en">Product</title>
    </meta>
</property>
```
