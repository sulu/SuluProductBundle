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

## Search index `products`

Products and variants are indexed into the SEAL index `products` (schema in `config/schemas/products.php`).
Number and date attributes get a field `attr_<key>`, options attributes a field `opt_<key>` holding option keys.
Those fields are appended at runtime from the attribute table (`ProductSchemaLoader`); the live index only
learns them when it is recreated. After adding or changing an attribute run:

    bin/console cmsig:seal:reindex --index products --drop

Until then products still index, but the new attribute does not filter. Recreation is never automatic.
The field list itself is cached and invalidated only through the ORM (a Doctrine entity listener on
`Attribute`); an attribute written by raw SQL needs `bin/console cache:pool:clear cache.app` as well.
The entry also expires after `AttributeIndexFieldProvider::CACHE_TTL` (5 minutes), because that
invalidation only reaches the kernel context it runs in. On Symfony below 7.4, or with any pool that
is not shared between the admin and the website process, the website kernel therefore sees a new
attribute field only after the TTL or after `bin/console cache:pool:clear cache.app` in the website
context. A shared pool (Redis, Memcached) avoids the delay.

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
the index's: `status`, `productFamilyId`, `attr_<key>`, `opt_<key>`. A parameter of the wrong shape
falls back to its default, `limit` is capped at 100 and `page` at `MAX_WINDOW / limit`, so that
`(page - 1) * limit + limit` never exceeds 10000. That is Elasticsearch's default
`index.max_result_window`; a deeper offset would be a search-phase exception on a public GET.

Field names come from the request, so `ProductSearcher` checks every one against the index schema and
silently drops what the schema does not allow: `filter`/`range` on a field that is not `filterable`,
`sort` on a field that is not `sortable` (the static schema marks only `authoredAt` and `changedAt`),
`facet`/`minmax` on a field without the `facet` flag. A custom controller building a
`ProductSearchQuery` itself gets the same check.

Hits default to variants plus products without variants (`ProductSearchQuery::HITS_LEAVES`);
`HITS_PRODUCTS` returns products including variant parents, `HITS_ALL` everything.
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
