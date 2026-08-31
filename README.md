# Sulu Product Bundle

## Variant URLs

A variant owns no route of its own. Referenced from a page, it resolves to its parent's URL plus a
query parameter carrying the variant code, so `/products/cable` with code `XY-2` resolves to
`/products/cable?variant=XY-2`.

The bundle only writes that URL — nothing reads the parameter back off the request. A project
selects the variant itself, which makes the key a contract between both sides: change it under
`sulu_product.variant_query_parameter` and the reading side has to match by hand.

```yaml
sulu_product:
    variant_query_parameter: 'variant' # default
```

A variant whose parent has no published route in the requested locale resolves without a `url`,
because an empty string would point at the site root.

## Association form overrides

The bundle generates a `product_associations` form with one field per configured
`sulu_product.association_types` key. A project overrides that form by shipping its own form
XML using the same `product_associations` key in a directory registered under
`sulu_admin.forms.directories` — the Sulu skeleton registers `config/forms` by default.

Rules for the declared fields:

- The field name must be `associations/<type>`, where `<type>` is a configured
  `sulu_product.association_types` key.
- Only the type `product_selection` is allowed — no other field type maps to product
  associations.
- A `properties` collection param declares which target properties resolve, in addition to the
  always-resolved `title`, `url`, `code`, `externalIdentifier`, `status`, `productFamily`,
  `position`, `image` and `shortDescription`. The last two are template fields, so a project whose
  `product_details.xml` drops them resolves without them instead of failing.
- Declared fields are never regenerated or relabeled, so add `<meta><title>` yourself.
- Invalid fields fail `cache:warmup`.

```xml
<!-- <project>/config/forms/product_associations.xml -->
<form xmlns="http://schemas.sulu.io/template/template">
    <key>product_associations</key>

    <properties>
        <property name="associations/alternative" type="product_selection">
            <params>
                <param name="properties" type="collection">
                    <param name="image" value="image"/>
                    <param name="code" value="code"/>
                </param>
            </params>
        </property>
    </properties>
</form>
```

Types the project does not declare keep their generated field, label and layout.
