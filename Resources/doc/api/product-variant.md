# Product Variant API

## cGET

URL: /admin/api/products/{productId}/variants?locale={locale}

cGet response will look the same as the `/api/products` api call.

## POST

URL: /admin/api/products/{productId}/variants?locale={locale}

Payload:

```json
{
    "name": "Variant Name",
    "number": "abc-123",
    "attributes": [
        {
            "attributeId": 1,
            "attributeValueName": "Attribute Value Name"
        }
    ],
    "prices": [
        {
            "price": 12.34,
            "currency": {
                "id": 1
            }
        }
    ]
}
```

## PUT

URL: /admin/api/products/{productId}/variants/{variantId}?locale={locale}

Payload looks exactly the same as POST.

## DELETE

URL: /admin/api/products/{productId}/variants/{variantId}
