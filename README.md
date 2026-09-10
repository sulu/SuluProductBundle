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

## Product documents in the `website` index

A product is indexed into Sulu's shared `website` index, next to pages and articles, and carries its
product data in one object field `product` (schema in `config/schemas/website.php`, merged into the
index Sulu ships). Only leaves become documents: a product without variants, and the variants of a
product that has them. A product with variants is represented by its variants and gets no document,
so it never shows up twice in a site search.

The `product` field holds:

| field | use |
|---|---|
| `code` | filterable, addresses a variant; the searchable copy sits in the index's `content` |
| `type` | `variant` or `product`, filterable, so a template can address a variant |
| `status`, `productFamilyId` | filterable and facet |
| `productFamilyName` | display |
| `changedAt` | the sortable field, since Sulu's own fields carry no `sortable` flag |
| `attributes_text_values` | one `<attributeKey>:<value>` entry per option key and text value, filterable and facet |
| `attributes_numeric_values.<attributeKey>` | the values of a number or date attribute, filterable and facet |
| `attributes` | display map `<attributeKey> => {label, value}` |

Option and text attributes need no field of their own, so adding one changes no schema. A number or
date attribute does: `NumericAttributeLister` reads the attribute table and `ProductSchemaLoader`
appends its field to `attributes_numeric_values`. The live index only learns it when it is recreated:

    bin/console cmsig:seal:reindex --index website --drop

Until then products still index, but the new attribute does not filter. Recreation is never automatic.
The attribute list is cached and invalidated only through the ORM (a Doctrine entity listener on
`Attribute`); an attribute written by raw SQL needs `bin/console cache:pool:clear cache.app` as well.
The entry also expires after `NumericAttributeLister::CACHE_TTL` (5 minutes), because that
invalidation only reaches the kernel context it runs in. On Symfony below 7.4, or with any pool that
is not shared between the admin and the website process, the website kernel therefore sees a new
attribute field only after the TTL or after `bin/console cache:pool:clear cache.app` in the website
context. A shared pool (Redis, Memcached) avoids the delay.

A variant document carries its own attribute values plus those of its parent that the family does not
mark variant-specific, its own title and code, and its parent's url and webspaces.

The admin index keeps the opposite rule: it holds the parent, because the edit view belongs to it,
and skips variants.

### Website catalogue search

Import the route with the `portal` type, so it is served under every portal URL, and configure the
template:

```yaml
# config/routes/sulu_product_website.yaml
sulu_product_website:
    type: portal
    resource: "@SuluProductBundle/config/routing_website.yaml"
```

```xml
<!-- config/webspaces/<key>.xml -->
<template type="product_search">products/search</template>
```

`GET /{locale}/products/search?q=...` renders that template with `query`, `hits`, `total`, `facets`,
`page`, `limit`, `filters`, `ranges` and `variantQueryParameter` (the configured
`sulu_product.variant_query_parameter`). Query parameters: `filter[<field>]`,
`range[<field>][min|max]`, `facet[]`, `minmax[]`, `sort[<field>]`, `page`, `limit`. Field names are
the index's paths, so a product field reads `product.status`, `product.attributes_text_values` or
`product.attributes_numeric_values.<attributeKey>`:

```
?filter[product.attributes_text_values]=colour:black
?range[product.attributes_numeric_values.weight][min]=20
?facet[]=product.attributes_text_values&facet[]=product.status
```

A parameter of the wrong shape falls back to its default, `limit` is capped at 100 and `page` at
`MAX_WINDOW / limit`, so that `(page - 1) * limit + limit` never exceeds 10000. That is
Elasticsearch's default `index.max_result_window`; a deeper offset would be a search-phase exception
on a public GET.

Field names come from the request, so `ProductSearcher` checks every one against the index schema and
silently drops what the schema does not allow: `filter`/`range` on a field that is not `filterable`,
`sort` on a field that is not `sortable`, `facet`/`minmax` on a field without the `facet` flag. A
custom controller building a `ProductSearchQuery` itself gets the same check. Every search is
restricted to the product documents of the current locale and webspace.

One count facet on `attributes_text_values` returns the values of every option and text attribute in
a single bucket list, capped at 100 values by the adapters, so it is meant for a result set already
narrowed by a family or a term.

Nested fields need an adapter that resolves a dotted path. Elasticsearch and Loupe do; the memory
adapter of a Sulu test setup does not, and throws on such a filter, so a project that wants to test
its catalogue filters runs its tests on Loupe.

A variant document's `url` is its parent's; the template appends `?<variantQueryParameter>=<code>`,
the same contract the "Variant URLs" section above describes.
Override `ProductSearchController::createQuery()` or replace `sulu_product.controller.website_search`
to change the contract. `ProductSearcher` is autowirable for custom controllers.

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
