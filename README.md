# Sulu Product Bundle

## Product route

The route field of a product lives in the `product_details` form and in the `product_variant`
overlay, not in the product template. Its field type and its params are configured once for the
whole project, so a form does not repeat them:

```yaml
sulu_product:
    route:
        type: route # default, e.g. "page_tree_route" for a route below a page
        params:
            route_schema: "/products/{implode('-', object)}" # default
```

`params` takes any key the configured field type understands and forwards it to the field as-is;
`route_schema` is the one the `route` field reads to generate the URL out of the fields tagged
`sulu.rlp.part`. A param configured here wins over the same param declared in the form XML. Both
forms get the same type and the same params.

`route_schema` defaults to the value above, so a product URL starts with `/products/` without any
configuration. A project overrides that key or adds params of its own, and the default stays for
every key the project does not set.

The same field is added invisibly to every product template, because `RoutableDataMapper` of the
content package reads the route property off the template metadata. A template declaring its own
`url` property keeps it and only receives the configured type and params.

## Variant URLs

A variant owns its route: the URL field is mandatory on the variant overlay, so every variant
carries an address of its own. A product with variants owns none, it is reached through its
variants, which is why the field is hidden for that type and the route guard drops one that
reaches such a product programmatically.

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
