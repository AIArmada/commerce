---
title: Configuration
---

# Configuration

All settings live in `config/links.php`.

## Database

| Key | Default | Description |
|---|---|---|
| `database.table_prefix` | `''` | Prefix applied when a table name is not listed in `tables`. |
| `database.json_column_type` | `jsonb` | Column type for JSON columns (`LINKS_JSON_COLUMN_TYPE`). |
| `database.tables.links` | `tracked_links` | Link table name. |
| `database.tables.clicks` | `tracked_link_clicks` | Click table name. |

## Defaults

| Key | Default | Description |
|---|---|---|
| `defaults.slug_length` | `7` | Length of auto-generated slugs. |
| `defaults.slug_alphabet` | unambiguous Base58-ish | Characters used for auto-generated slugs. |
| `defaults.redirect_status` | `302` | Redirect status for `/go/{slug}`. Prefer `302`: `301` responses are cached by browsers and undercount clicks. |

## Owner

| Key | Default | Description |
|---|---|---|
| `owner.enabled` | `false` | Enable owner scoping. |
| `owner.include_global` | `false` | Include global rows in scoped reads. |
| `owner.auto_assign_on_create` | `true` | Assign the current owner to new links automatically. |

## Tracking

| Key | Default | Description |
|---|---|---|
| `features.tracking.ip.enabled` | `true` | Capture visitor IPs. |
| `features.tracking.ip.anonymize` | `false` | Zero the last octet (IPv4) or last 80 bits (IPv6). |
| `features.tracking.user_agent.enabled` | `true` | Parse device/browser/OS from the user agent. |
| `features.tracking.user_agent.store_raw` | `true` | Persist the raw user-agent string. |
| `features.tracking.bots.record` | `true` | Persist bot clicks. When `false`, bot visits still redirect but leave no click row and no counts. |
| `features.tracking.bots.count` | `false` | Include bot clicks in `human_clicks` and click limits. |

## Security

| Key | Default | Description |
|---|---|---|
| `features.security.require_https` | `true` | Reject non-https destinations. |
| `features.security.allowed_hosts` | `[]` | When non-empty, destinations must match one of these hosts (`LINKS_ALLOWED_HOSTS`, comma-separated). |
| `features.security.reserved_slugs` | admin/api/app/... | Slugs that cannot be registered. |

> [!WARNING]
> The redirect route is a public open redirector for admin-created destinations. Keep link management behind authentication, and set `allowed_hosts` when links should only point at known merchants.

## Retention

| Key | Default | Description |
|---|---|---|
| `features.retention.prune_clicks_after_days` | `365` | Default window for `links:prune-clicks`. Set `null` to keep clicks forever. |

## Routing

| Key | Default | Description |
|---|---|---|
| `routing.prefix` | `go` | URL prefix for cloaked links. |
| `routing.domain` | `null` | Optional dedicated domain (e.g. `go.example.com`). |
| `routing.middleware` | `['web']` | Middleware for the redirect route. |
| `routing.name` | `links.redirect` | Route name used by `Link::cloakedUrl()`. |
| `routing.signature_ttl_minutes` | `43200` | TTL for `GenerateLinkUrl` signed URLs (30 days). |

## Read next

- [Usage](04-usage.md)
