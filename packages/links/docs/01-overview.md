---
title: Overview
---

# Links Package

## Purpose

The `aiarmada/links` package is generic tracked-link management for Laravel. It owns link records, cloaked redirect URLs, click capture, expiry and click limits, and the domain events third-party packages listen to.

## What this package owns

- Tracked links: names, slugs, destinations, UTM defaults, destination parameters, expiry, click limits, activation lifecycle
- The cloaked redirect route (`/go/{slug}` by default) with parameter merging and optional per-link signed URLs
- Click records: IP, user agent, device/browser/OS, bot flags, referrer, UTM values, ad click IDs, subject reference
- Consumer seams: subject morphs, the link-gate policy contract, and signed-URL generation
- Counter caches (`total_clicks`, `human_clicks`) and first/last click timestamps
- Click retention pruning via `links:prune-clicks`
- Domain events for every lifecycle transition and click

## What this package does not own

- Behavioural analytics dashboards, funnels, or alerting; see [`aiarmada/signals`](../../signals/docs/01-overview.md)
- Inbound affiliate programs, commissions, or payouts; see [`aiarmada/affiliates`](../../affiliates/docs/01-overview.md)
- Filament admin surfaces; those belong to `aiarmada/filament-links`

## Related packages

- [`aiarmada/filament-links`](../../filament-links/docs/01-overview.md) — Filament link management UI
- [`aiarmada/affiliate-network`](../../affiliate-network/docs/01-overview.md) — rides on tracked links for offer redirects
- [`aiarmada/signals`](../../signals/docs/01-overview.md) — optional analytics sink for `LinkClicked` events
- [`aiarmada/commerce-support`](../../commerce-support/docs/01-overview.md) — owner scoping and shared utilities

## Main models services or surfaces

- **Models** — `Link`, `LinkClick`
- **Actions** — `CreateLink`, `UpdateLink`, `DeactivateLink`, `ReactivateLink`, `ResolveLink`, `RecordLinkClick`, `RedirectToLink`
- **Events** — `LinkCreated`, `LinkUpdated`, `LinkDeactivated`, `LinkReactivated`, `LinkClicked`, `LinkExpired`, `LinkClickLimitReached`
- **Contracts** — `SlugGeneratorInterface`, `BotDetectorInterface`, `UserAgentParserInterface`, `LinkGateInterface`, each with a swappable default
- **HTTP surface** — `GET /go/{slug}` redirect route (prefix, domain, and middleware are configurable); per-link signed URLs via `GenerateLinkUrl`
- **Console** — `links:prune-clicks` retention command

## Owner scoping and security notes

- Links and clicks are owner-aware and follow the `commerce-support` owner-boundary rules
- Slugs live in a global namespace because the public redirect route resolves without owner context
- The redirect route records clicks without authentication by design; link *management* must stay behind auth
- Destination URLs require `https` by default and support an allowed-hosts allowlist

## Key Features

- Auto-generated or custom slugs with reserved-word protection and global uniqueness
- Click capture with device/browser/OS parsing and bot detection
- Bots are recorded but flagged, and excluded from human counts and click limits by default
- Expiry dates, maximum click counts, and activate/deactivate lifecycle
- Per-link UTM defaults merged into the destination on redirect
- Tracking failures never break the redirect; they are reported and the visitor still lands

## Requirements

- PHP 8.4+
- Laravel 12+
- `aiarmada/commerce-support`

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Troubleshooting](99-troubleshooting.md)
- [Filament Links overview](../../filament-links/docs/01-overview.md)
