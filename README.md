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

## Variant publishing

A variant is published on its own, from the variants tab of its product: select variants in the
list and use "Publishing" in the toolbar, which needs the live permission. Publish skips variants
without content in the current locale and variants already published without changes, unpublish
only acts on published ones. The list marks unpublished variants and variants shown in a fallback
locale, the same way the article list does.

A variant renders its product's content, so it is only live together with its product: publishing
a variant is refused while its product is not published in that locale, and unpublishing the
product unpublishes its variants there. Publishing the product leaves its variants as they are,
and saving a variant does not mark its product as changed. The website lists and routes only
published variants whose product is published in that locale.

The action calls `POST /admin/api/products/{parentId}/variants/{id}?action=publish` (or
`unpublish`), which answers `409` when the transition is not available, for example unpublishing
a variant that was never published or publishing one whose product is not published. The list
shows the reason for each variant it could not change.

## Variant attributes

A family attribute with the "variant" toggle is held by the product that carries the article: a
variant, or a product without variants. A product with variants holds only the shared attributes,
a variant only the variant attributes. The details form, its schema and the required check follow
the product type.

## Product in the website

A variant is a page of its own route but has no content of its own: a dimension content enhancer
resolves it with the parent's template, excerpt and SEO data, so `content` and `extension` are the
parent's content tab. Its `product` namespace carries:

- `product`: the parent, with `product.title`, the master data (`code`, `status`, ...),
  `product.attributes` (its own values), `product.associations` and `product.variants`. A product
  with variants owns no route, so it has no `product.url`.
- `product.currentVariant`: the variant itself: `title`, `url`, `code`, ..., `attributes` (the
  variant's own values), `associations`.
- `product.variants`: every published variant of the parent with `title`, `url`, `code`, `status`
  and `position`. A project adds properties, which are merged into these defaults:

```yaml
sulu_product:
    variants:
        properties:
            image: product.image
```

A product without variants resolves as itself, with `product.url` and without `variants` or
`currentVariant`. A variant resolved as a reference (a product selection) resolves as itself.

To show a variant's attributes together with its product's, merge them in the template; the
variant's value wins on the same attribute:

```twig
{% set attributes = sulu_product_merge_attributes(product.attributes, product.currentVariant.attributes|default({})) %}
{% set groups = attributes|sulu_product_attribute_groups %}
```

To render a single attribute value outside those groups, for example an option value in a variant
switcher, format it with `sulu_product_format_attribute_value`:

```twig
{{ attributeValue|sulu_product_format_attribute_value }}
```

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
