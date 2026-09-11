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
| `productFamilyId` | filterable and facet |
| `attributes_text_values` | one `<attributeKey>:<value>` entry per option key and text value, filterable and facet |
| `attributes_numeric_values.<attributeKey>` | the values of a number or date attribute, filterable and facet |

Option and text attributes need no field of their own, so adding one changes no schema. A number or
date attribute does: `ProductSchemaLoader` reads the attribute table and appends its field to
`attributes_numeric_values`. The live index only learns it when it is recreated:

    bin/console cmsig:seal:reindex --index website --drop

Until then products still index, but the new attribute does not filter. Recreation is never automatic.
The schema is read once per container, so a long-running process such as a Messenger worker sees a
new attribute only after a restart.

A variant document carries its own attribute values plus those of its parent that the family does not
mark variant-specific, its own title and code, and its parent's url and webspaces.

The admin index keeps the opposite rule: it holds the parent, because the edit view belongs to it,
and skips variants.

### Searching products

The bundle ships no route, controller or template for a catalogue page. A project builds its own
overview controller on SEAL's `EngineInterface`, which is autowirable, and restricts the search to
the product documents of the current locale and webspace:

```php
use CmsIg\Seal\Search\Condition\Condition;
use CmsIg\Seal\Search\Facet\Facet;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Search\ProductIndex;

$result = $engine->createSearchBuilder(ProductIndex::NAME)
    ->addFilter(Condition::equal('resourceKey', ProductInterface::RESOURCE_KEY))
    ->addFilter(Condition::equal('locale', $locale))
    ->addFilter(Condition::equal('webspaces', $webspaceKey))
    ->addFilter(Condition::search($term))
    ->addFilter(Condition::equal(ProductIndex::textValuesPath(), ProductIndex::textValue('colour', 'black')))
    ->addFilter(Condition::greaterThanEqual(ProductIndex::numericValuePath('weight'), 20.0))
    ->addFacet(Facet::count(ProductIndex::textValuesPath()))
    ->limit(24)
    ->offset(0)
    ->getResult();
```

Field names are the index's paths, so a product field reads `product.productFamilyId`,
`product.attributes_text_values` or `product.attributes_numeric_values.<attributeKey>`; `ProductIndex`
builds the attribute paths and values.

A controller that takes field names from the request checks them against the index schema
(`filterableFields`, `facetFields`, `sortableFields` of `Schema::$indexes['website']`) before they
reach a condition, because an adapter either fails the request on an unknown field or takes the name
into its own filter syntax. It also caps the page size and the page number: Elasticsearch rejects an
offset beyond `index.max_result_window` (10000 by default) with a search-phase exception.

One count facet on `attributes_text_values` returns the values of every option and text attribute in
a single bucket list, capped at 100 values by the adapters, so it is meant for a result set already
narrowed by a family or a term.

Nested fields need an adapter that resolves a dotted path. Elasticsearch and Loupe do; the memory
adapter of a Sulu test setup does not, and throws on such a filter, so a project that wants to test
its catalogue filters runs its tests on Loupe.

A variant document's `url` is its parent's, so the template appends
`?<variantQueryParameter>=<code>`, the same contract the "Variant URLs" section above describes.

Sulu's own site search needs none of this: products are documents of the shared `website` index, so
`sulu_search.website_search` finds them next to pages and articles.

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
