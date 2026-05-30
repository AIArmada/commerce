---
title: Portal
---

# Affiliate Portal

The plugin includes a self-service Filament panel for affiliates.

## Overview

The portal allows affiliates to:

- view a dashboard with earnings, click, and conversion summaries,
- generate affiliate links,
- review conversion history,
- review payout history,
- register a new affiliate account when portal registration is enabled.

## Enabling the Portal

Register `AIArmada\FilamentAffiliates\AffiliatePanelProvider::class` in your application and configure the panel in `config/filament-affiliates.php`:

```php
'portal' => [
    'panel_id' => 'affiliate',
    'path' => 'affiliate',
    'domain' => 'affiliate.example.com',
    'brand_name' => 'Affiliate Portal',
    'primary_color' => '#6366f1',
],
```

## Portal pages

Pages are feature-gated through `filament-affiliates.portal.features`:

- `dashboard` -> `PortalDashboard`
- `profile` -> `PortalProfile`
- `links` -> `PortalLinks`
- `programs` -> `PortalPrograms`
- `conversions` -> `PortalConversions`
- `payouts` -> `PortalPayouts`
- `support_compliance` -> `PortalSupport`

If `filament-affiliates.portal.registration_enabled` is `true`, the panel uses `PortalRegistration` as the registration page.

## Panel behavior

`AffiliatePanelProvider` configures:

- panel id/path/domain/branding from config,
- Filament auth middleware,
- optional login and registration pages,
- configured auth guard,
- bundled portal CSS asset.

## Portal configuration example

```php
'portal' => [
    'panel_id' => env('AFFILIATES_PORTAL_PANEL_ID', 'affiliate'),
    'path' => env('AFFILIATES_PORTAL_PATH', 'affiliate'),
    'domain' => env('AFFILIATES_PORTAL_DOMAIN'),
    'brand_name' => env('AFFILIATES_PORTAL_BRAND_NAME', 'Affiliate Portal'),
    'primary_color' => env('AFFILIATES_PORTAL_PRIMARY_COLOR', '#6366f1'),
    'login_enabled' => env('AFFILIATES_PORTAL_LOGIN_ENABLED', true),
    'registration_enabled' => env('AFFILIATES_PORTAL_REGISTRATION_ENABLED', true),
    'auth_guard' => env('AFFILIATES_PORTAL_AUTH_GUARD', 'web'),
    'features' => [
        'dashboard' => true,
        'profile' => true,
        'links' => true,
        'programs' => true,
        'conversions' => true,
        'payouts' => true,
        'support_compliance' => true,
    ],
],
```

When `affiliates.features.commission_tracking.enabled` is `false`, portal payouts are automatically disabled by the panel provider.

## Access model

By default, portal pages require authentication and resolve affiliate context from the current user. If a user has no linked affiliate, pages should render empty-state behavior.

If stricter enforcement is needed (for example affiliate-only guard middleware), extend `AffiliatePanelProvider` and add custom auth middleware.

## Notes

- Portal filters are not authorization by themselves.
- Server-side write validation should continue to enforce owner-safe access.
