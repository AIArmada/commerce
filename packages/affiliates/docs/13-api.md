---
title: Public API
---

# Public API

## Enable API

```php
'api' => [
    'enabled' => env('AFFILIATES_API_ENABLED', false),
    'prefix' => env('AFFILIATES_API_PREFIX', 'api/affiliates'),
    'middleware' => ['api', 'throttle:60,1'],
    'token' => env('AFFILIATES_API_TOKEN'), // required Bearer [REDACTED]
],
```

## Endpoints

- `GET /api/affiliates/{code}/summary`
- `POST /api/affiliates/{code}/links`
- `GET /api/affiliates/{code}/creatives`
- `POST /api/affiliates/{code}/programs/{id}/join`
- `GET /api/affiliates/{code}/programs/{id}/membership`
- `GET /api/affiliates/programs`
- `GET /api/affiliates/programs/{id}/catalog`

## Summary Endpoint

`GET /api/affiliates/{code}/summary` returns the affiliate profile, funnel, UTM split, and totals. Totals convert to `affiliates.currency.default` when conversions span currencies, and null when an exchange rate is missing — `by_currency` always holds the exact legs:

```json
{
  "totals": {
    "commission_minor": 20000,
    "revenue_minor": 200000,
    "conversions": 2,
    "ltv_minor": 100000,
    "currency": "USD",
    "converted": true,
    "by_currency": {
      "USD": {"conversions": 1, "revenue_minor": 100000, "commission_minor": 10000}
    }
  }
}
```

## Link Endpoint

`POST /api/affiliates/{code}/links` accepts subject-aware metadata:

```json
{
  "url": "https://example.com/products/sku-1001",
  "link_style": "branded",
  "link_label": "summer",
  "params": {"utm_campaign": "spring-launch"},
  "subject_type": "product",
  "subject_key": "SKU-1001",
  "subject_instance": "web",
  "subject_title_snapshot": "Pro Plan",
  "subject_metadata": {"category": "subscriptions"}
}
```

Success response:

```json
{
  "id": "uuid",
  "link": "https://example.com/go/saifreviews/summer-k7m4",
  "subject_type": "product",
  "subject_key": "SKU-1001"
}
```

## Owner Context

If owner scoping is enabled and global rows are disabled, API requests require resolved owner context; otherwise, endpoints return `400` with `Owner context required`.

## Program Catalog Endpoint

`GET /api/affiliates/programs/{id}/catalog` returns version `v2`, including
raw program creatives alongside subjects and commission rules. Each creative
contains its merchant ID, type, name, description, nullable absolute
`asset_url`, nullable `destination_url`, dimensions, tracking code, and
metadata. No affiliate-specific HTML is exported. Only active public programs
are readable. See [Program catalog](14-catalog.md) for the required contract.
