# Upgrade

## dev-develop

### ProductTestData

Changed default locale of product test data from `de` to `en`. This does
not affect product translations, since they were `en` by default
(inconsistency).

## 0.16.2

### Product Routing

ProductWebsiteController routing has been replaced by SuluRouteBundle.
Therefore you'll need to specify the desired route as described in
[README](README.md#custom-routing)


### Product Factory

A new method for creating api addon products has been added.
If you are extending the product factory you'll need to implement the
method `createAddonProductApiEntity`.

### ApiProduct

The constructor of ApiProduct entity has changed.
Now an instance of `ProductFactoryInterface` is needed.
Therefore, if you inherit from an ApiProduct you'll

* need to inject the factory to the API entity and also
* need to overwrite the ApiAddonProduct which extends the ApiProduct.


## 0.15.0

### Formatter locale

Since the formatter has been moved from pricing to product bundle an
extra config variable needs to be defined.

```
sulu_product:
    default_formatter_locale: 'en'
```

## 0.14.0

### Added schema for products api.

SuluValidationBundle has been included and as first step a schema for
`GET products by id` has been created. The schema now includes a locale for
products:

If you have requested a product without providing the locale, you now
need to add a locale to your call e.g.

```
    GET /admin/api/products/1?locale=en
```

### Product type cleanups

### New product type `variant`

A new product type `Variant` has been added. We also changed structure
of type translations, that's why it's recommended to reimport fixtures:

SQL:

```
SET FOREIGN_KEY_CHECKS=0;
TRUNCATE TABLE pr_types;
DROP TABLE IF EXISTS pr_type_translations;
SET FOREIGN_KEY_CHECKS=1;
```

Now re-import fixtures with the following command:

```
bin/console doctrine:schema:update
bin/console doctrine:fixtures:load --fixtures=vendor/sulu/product-bundle/Sulu/Bundle/ProductBundle/DataFixtures/ORM/ProductTypes --append
```

### Type translations

Type translations have been moved from the database to translations
file. You probably will have to adapt tests fixtures or custom sql
statements.

### Product type cleanups

Removed the inversed side of relation `type.products`, since only the
relation `product.types` should be used.
