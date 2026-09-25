---
title: Troubleshooting
---

# Troubleshooting

## Redirects return 410 for a link that exists

Check, in order: `deactivated_at` is set, `expires_at` is past, `human_clicks` reached `max_clicks`, or the bound link gate blocks the slug. Any of these makes the slug unresolvable. The `slug` route pattern also only accepts `[A-Za-z0-9_-]+`. `404` means the slug itself is unknown; `403` means a required URL signature is missing or expired.

## Click counts look low

- Confirm the redirect status is `302`. A `301` is cached by browsers, so repeat visits never hit the route.
- Bots are excluded from `human_clicks` by default; compare against `total_clicks`.
- When `features.tracking.bots.record` is `false`, bot visits leave no click row at all.

## Destination rejected as invalid

Destinations must be valid URLs with an `http`/`https` scheme, no embedded credentials, `https` when `require_https` is true, and a host in `allowed_hosts` when that list is non-empty.

## Slug already taken

Slugs are globally unique across owners because the public redirect route has no owner context. Pick a more specific slug or let the package auto-generate one.

## UTM values missing on the merchant side

Precedence is incoming query, then the destination's own query, then `utm_defaults`. A value already present in the destination URL is never overwritten by defaults.

## Clicks table growing too fast

Schedule `links:prune-clicks` and lower `features.retention.prune_clicks_after_days`, or disable bot recording if crawler traffic dominates.
