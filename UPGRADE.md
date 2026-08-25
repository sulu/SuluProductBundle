# Upgrade

## Product data moved to a root-level `product` namespace

The four `extension.*` keys the website resolvers emitted are now one root-level `product` key,
a sibling of `content`, `view` and `extension`. Details master-data fields are flattened directly
onto it, alongside three fixed sub-keys:

| Before | After |
| --- | --- |
| `extension.product.code` | `product.code` |
| `extension.product.status` | `product.status` |
| `extension.product.externalIdentifier` | `product.externalIdentifier` |
| `extension.product.productFamily` | `product.productFamily` |
| `extension.product.<details field>` | `product.<details field>` (e.g. `product.documents`) |
| `extension.attributes` | `product.attributes` |
| `extension.associations` | `product.associations` |
| `extension.variants` | `product.variants` |

`sulu_product_load()` returns the same envelope, so its callers move with the table.

`product.attributes` changed shape. It was a list of groups, each holding its attributes; it is now
a flat map keyed by attribute key, and each entry carries `key`, `label`, `type`, `value`,
`formattedValue`, `position` and a `group` of `{key, label}`:

```twig
{{ product.attributes.impedance.formattedValue }}

{% for group in product.attributes|sulu_product_attribute_groups %}
    {{ group.label }}
    {% for attribute in group.attributes %}{{ attribute.label }}{% endfor %}
{% endfor %}
```

Grouping for display is now the `sulu_product_attribute_groups` Twig filter, registered by the
`sulu_product.product_attribute_twig_extension` service. Group order is the attribute groups'
database order (sorted by group id, numerically); attributes within a group sort by their
`position`.

A `details/<field>` may no longer be named `attributes`, `associations`, `variants`, `code`,
`externalIdentifier`, `productFamily` or `status` — those names are reserved by the `product`
namespace. Using one now throws `InvalidProductDetailsFieldException` while form metadata is
built (at cache warmup), not at container compile.

`product.variants` items still do not flatten. A template reads `variant.content.code`, not
`variant.code` — the `{content, view, resource}` wrapper survives the move to the `product`
namespace. This is deliberate, not a regression: Sulu's `replaceNestedContentViews()` only walks
the `content` tree, so a nested content resolver's output — variants included — is never
flattened automatically, no matter where in the payload it sits.

Blocks that reference products — smart content, product selections — should configure a
`properties` param. Without one they request the full payload and resolve attributes and variants
per item.

This requires a Sulu version carrying the `placement` attribute on the
`sulu_content.content_resolver` tag.

## `product.productFamily` is a wrapper object, not a UUID

`product.productFamily` used to be the family's UUID string. It is now a `ProductFamilyWrapper`
object. A template reads `product.productFamily.name` (previously it had to look the UUID up
itself).

## `product.attributes` semantics changed beyond the list-to-map shape

Beyond becoming a flat map (described above), three semantics changed:

- for a `TYPE_DATE` attribute, `value` used to be an ISO `Y-m-d` string; it is now the raw
  float timestamp.
- `formattedValue` used to be `null` unless the attribute had a `displayFormat`; it is now
  always the display string.
- a value that formats to nothing, and a group left empty by that, are no longer returned at
  all (previously they came through empty).

## `ProductTwigExtension::__construct()` lost its sixth argument

The constructor no longer takes a `MeasurementRegistry`. Any project decorating this service
must drop that argument from its decorator.

## Admin API keeps the old `productFamily` shape

`ProductDetailsNormalizer` (admin API) still emits `productFamily` as a UUID string, while the
website content resolvers emit `product.productFamily` as a `ProductFamilyWrapper` object. The
same key has two different shapes depending on which side reads it. This is intended.
