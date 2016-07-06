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
    objects:
        product:
            model: Acme\Bundle\ProductBundle\Entity\Product
            repository: Acme\Bundle\ProductBundle\Entity\ProductRepository
```

## Localization

Multiple locales for managing products can be defined in the config. 
If non defined, 'en' is taken as default.

If the users language matches any of the given locales, that one is displayed in the admin area.
Else the `fallback_locale` parameter is used.

## Custom Entity and Repository

If you'd like to overwrite the sulu product entity you simply need to set the
`objects.product` parameters.

## Shop templates

For shop purposes `template` can be used to define the template for displaying
product details.
