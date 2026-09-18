---
title: Troubleshooting
---

# Troubleshooting

## Resource does not appear in navigation

Confirm the plugin is registered on the panel and `filament-links.features.links` is `true`.

## Slug rejected as taken

Slugs are globally unique across owners. Pick a more specific slug or clear the field to auto-generate.

## Destination rejected

The core package requires `https` destinations by default and enforces `links.features.security.allowed_hosts` when set. See the [core troubleshooting](../../links/docs/99-troubleshooting.md).

## Clicks missing for a link

Bot visits leave no rows when `links.features.tracking.bots.record` is `false`, and recording failures are reported rather than shown. Check the application logs.
