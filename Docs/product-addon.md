# Product Addon API

## GET

URL: /products/{productId}/addons

```json
{
	"_embedded": {
		"addons": [{
			"id": 1,
			"addon": {
				"id": 4,
				"name": "Addon 1",
				"type": {
					"id": 3,
					"name": "Produkt Erweiterung"
				}
			},
			"prices": [{
				"id": 1,
				"currency": {
					"name": "Euro",
					"id": 2,
					"code": "EUR",
					"number": "978"
				},
				"price": "222"
			}]
		}]
	}
}
```

## POST

URL: /products/{productId}/addons

```json
{
	"addon": 1234,
	"prices": [
        {
            "value": 1111,
            "currency": "EUR"
        },
        {
            "value": 1234,
            "currency": "CHF"
        }
	]
}
```

## PUT

URL: /products/{productId}/addons

```json
{
	"addon": 1234,
	"prices": [
        {
            "value": 1111,
            "currency": "EUR"
        },
        {
            "value": 1234,
            "currency": "CHF"
        }
	]
}
```

## DELETE

URL: /products/{productId}/addons/{addonId}
