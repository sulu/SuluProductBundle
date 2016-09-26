# Upgrade

## 0.15.0

### Formatter locale

Since the formatter has been moved from pricing to product bundle an
extra config variable needs to be defined.

```
sulu_product:
    default_formatter_locale: 'en'
```

## 0.14.0

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
