# Development

This documentation contains information about product-bundle development.

## Constants

### Type

Types are loaded into the database the with data-fixtures.
They are accessible as key to id map with the following parameter: `sulu_product.product_types_map`

e.g.

```
[
  'PRODUCT' => 1,
  'PRODUCT_WITH_VARIANTS' => 2,
  'PRODUCT_ADDON' => 3,
  'PRODUCT_SET' => 4,
  'PRODUCT_VARIANT' => 5,
]
```

### Status

Statuses are loaded into the database with data-fixtures.
All available statuses are defined in the Status entity as constants.

## Preview and Serialization

Preview uses the serialized product entities for displaying changes. The
serialization properties are defined in xml files in
`Resources/config/serializer`. If a new property is added to the
product entity and you'd like the preview to notice, the property also
needs to be added to the serialization xml.
