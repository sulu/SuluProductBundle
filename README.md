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
product or removing its translation unpublishes its variants there. Publishing the product leaves
its variants as they are, and saving a variant does not mark its product as changed.

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

A variant has a route but no content of its own, so its URL renders its product: the route passes
the product as the page's `object` and the variant as the `variant` route default. `resource`,
`content` and `extension` are the product's, `localizations` link the variant's own URLs in the
other locales. `sulu_product.product_localizations_resolver` builds them for the `products`
resource key; a project changes them by decorating that service. The `product` namespace carries:

- `product`: the parent, with `product.title`, the master data (`code`, `status`, ...),
  `product.attributes` (its own values), `product.associations` and `product.variants`. A product
  with variants owns no route, so it has no `product.url`.
- `product.currentVariant`: the variant the URL asked for: `title`, `url`, `code`, ...,
  `attributes` (the variant's own values), `associations`.
- `product.variants`: every published variant of the parent with `title`, `url`, `code`, `status`
  and `position`. A project adds properties, which are merged into these defaults:

```yaml
sulu_product:
    variants:
        properties:
            image: product.image
```

A product without variants resolves as itself, with `product.url` and without `variants` or
`currentVariant`. A variant resolved as a reference (a product selection) or as a teaser resolves
as itself, without its product's content.

`product.attributes` maps each attribute key to its `attribute` and its `value`. The value has the
shape the admin API uses: a number, a text, a `Y-m-d` date or an option key, however many rows the
attribute's type stores it in:

```twig
{{ product.attributes.weight.value }}
{{ product.attributes.weight.attribute.key }}
```

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

## Product family selection

`product_family_selection` (a list) and `single_product_family_selection` store family uuids. In
the website each family resolves to the shape of a product's `productFamily`:
`{uuid, externalIdentifier, name, image}`, with `name` in the requested locale or `null` and
`image` resolved as media or `null`. A family that no longer exists is left out of the list.

```xml
<property name="productFamilies" type="product_family_selection">
    <meta>
        <title lang="en">Product families</title>
    </meta>
</property>
```

## Product documents in the `website` index

A product is indexed into Sulu's shared `website` index, next to pages and articles. Only leaves
become documents: a product without variants, and the variants of a product that has them. A product
with variants is represented by its variants and gets no document of its own. A variant document
carries its own title and its parent's webspaces, and the `url` the "Variant URLs" section above
describes. The product code is searchable through the document's `content`.

The admin index keeps the opposite rule: it holds the parent, because the edit view belongs to it,
and skips variants.

### Product filters

A project with a catalogue page turns on the product family and the attribute values as filter
fields of an object field `product`, which `ProductSchemaLoader` adds to the index Sulu ships:

```yaml
sulu_product:
    search:
        website:
            additional_product_filters: true # default: false
```

Switching the option on or off adds or removes the whole `product` field, so the index needs
`bin/console cmsig:seal:reindex --index website --drop` afterwards.

| field | use |
|---|---|
| `productFamilyId` | filterable and facet |
| `attributes_text_values` | one `<attributeKey>:<optionKey>` entry per value of a filterable options attribute, filterable and facet |
| `attributes_numeric_values.<attributeKey>` | the values of a filterable number or date attribute, filterable and facet |

Only attributes with Filterable on get a filter field. An attribute key is reduced to letters, digits
and `_`, and prefixed with `a_` unless it starts with a letter. A variant carries its own values plus
those of its parent that the family does not mark variant-specific. The values of every attribute,
filterable or not, are added to the searchable `content` as `<label>: <value>`.

The product family name, external identifier, short description and details image are indexed
without this option: the first three as `content`, the image where neither the template nor the
excerpt has one.

Options attributes need no field of their own, so adding one changes no schema. A number or date
attribute does, when it is created filterable or its Filterable flag or key changes: the loader
reads the attribute table, and the live index only learns the change when it is recreated, which is
never automatic:

    bin/console cmsig:seal:reindex --index website --drop

Run it before a product with a value for the new attribute is published. An engine with a strict
mapping, such as Elasticsearch, rejects a document that carries a field its index does not know, so
until the recreation such a product does not index at all. The schema is read once per container, so
a long-running process such as a Messenger worker sees a new attribute only after a restart.

### Searching products

The bundle ships no route, controller or template for a catalogue page. A project builds its own
overview controller on SEAL's `EngineInterface`, which is autowirable, and restricts the search to
the product documents of the current locale and webspace:

```php
use CmsIg\Seal\Search\Condition\Condition;
use CmsIg\Seal\Search\Facet\Facet;
use Sulu\Product\Domain\Model\ProductInterface;

$result = $engine->createSearchBuilder('website')
    ->addFilter(Condition::equal('resourceKey', ProductInterface::RESOURCE_KEY))
    ->addFilter(Condition::equal('locale', $locale))
    ->addFilter(Condition::equal('webspaces', $webspaceKey))
    ->addFilter(Condition::search($term))
    ->addFilter(Condition::equal('product.attributes_text_values', 'colour:black'))
    ->addFilter(Condition::greaterThanEqual('product.attributes_numeric_values.weight', 20.0))
    ->addFacet(Facet::count('product.attributes_numeric_values.weight'))
    ->limit(24)
    ->offset(0)
    ->getResult();
```

Nested fields need an adapter that resolves a dotted path, such as Elasticsearch or Loupe; the
memory adapter does not.

Sulu's own site search needs none of this: it finds products next to pages and articles.

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
