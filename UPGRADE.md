# Upgrade

## dev-develop

### New Product type variant

A new product type `Variant` has been added. You either need to reimport
the type fixtures, or simply execute the following sql:

```
INSERT INTO pr_types VALUES();
SET @id = last_insert_id();
INSERT INTO pr_type_translations (name, locale, idTypes) VALUES ('Variante', 'de', @id);
INSERT INTO pr_type_translations (name, locale, idTypes) VALUES ('Variante', 'de_ch', @id);
INSERT INTO pr_type_translations (name, locale, idTypes) VALUES ('Variant', 'en', @id);
```
