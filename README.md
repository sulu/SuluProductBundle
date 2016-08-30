SuluProductBundle [![Build Status](https://travis-ci.org/sulu/SuluProductBundle.svg?branch=develop)](https://travis-ci.org/sulu/SuluProductBundle)
=================

# Configuration

Sample configuration:

```
sulu_product:
    fallback_locale: de
    locales:
        - de
        - en
    template: AcmeShopBundle:views:templates/productdetail.html.twig
    default_currency: '%default_currency%'
    display_recurring_prices: true
    objects:
        product:
            model: Acme\Bundle\ProductBundle\Entity\Product
            repository: Acme\Bundle\ProductBundle\Entity\ProductRepository
    fixtures:
        attributes:
            - src/Acme/Bundle/ProductBundle/DataFixtures/attributes.xml
```

## Localization

Multiple locales for managing products can be defined in the config. 
If non defined, 'en' is taken as default.

If the users language matches any of the given locales, that one is displayed in the admin area.
Else the `fallback_locale` parameter is used.

## Recurring prices

The property recurring prices can be disabled via parameter `display_recurring_prices`.
This option just only hide the UI elements for the user.

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
