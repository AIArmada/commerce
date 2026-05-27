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

## Portal Pages

### Dashboard

The main portal dashboard displays:

- Lifetime earnings summary
- Monthly performance chart
- Recent conversions list
- Pending payout amount
- Quick link generator

`PortalDashboard` displays:
namespace AIArmada\FilamentAffiliates\Pages\Portal;
- total earnings,
- pending earnings,
- available earnings,
- total clicks,
- total conversions,
- conversion rate,
- recent conversions,
- recent payouts.
    protected function getStats(): array
    {
use AIArmada\FilamentAffiliates\Pages\Portal\PortalDashboard;
            Stat::make('Total Earnings', $this->affiliate->totalEarnings()),
class PortalDashboard extends Page
            Stat::make('Pending', $this->affiliate->pendingCommission()),
    }
}
```
            'totalEarnings' => $this->getTotalEarnings(),
            'pendingEarnings' => $this->getPendingEarnings(),
            'availableEarnings' => $this->getAvailableEarnings(),

- Generate links for any URL
- Copy to clipboard
- QR code generation
- Link analytics (clicks, conversions)

```php
`PortalLinks` exposes the affiliate’s default link and a header action for generating a custom target URL.
{
- Link generation is delegated to `AffiliateLinkGenerator`.
- If you configure `affiliates.links.allowed_hosts`, generated links must stay within that allowlist.
- The page falls back to the configured query parameter (`aff` by default) when it needs a simple default link for display.
            targetUrl: $this->url,
            campaign: $this->campaign,
use AIArmada\FilamentAffiliates\Pages\Portal\PortalLinks;

class PortalLinks extends Page

        $this->createdLink = $link->full_url;
    }
        $this->generatedLink = app(AffiliateLinkGenerator::class)->generate(
            $affiliate->code,
            $this->targetUrl,

- Date/time of conversion
- Reference (`external_reference`)
- Total value (`value_minor`)
- Commission amount
- Status (pending, approved, paid)
- Filter by date range and status
`PortalConversions` renders an affiliate-scoped table with:
### Payouts

Track payout history:

- Payout date
- Amount
- default descending sort by occurrence time
- summary values for total conversions, total earnings, and pending earnings
- Status
- Transaction reference

`PortalPayouts` renders an affiliate-scoped payout table with:

Self-registration for new affiliates:

class RegistrationPage extends Page
{
- Paid-at timestamp

The payout page is automatically disabled when `affiliates.features.commission_tracking.enabled` is `false`, even if `filament-affiliates.portal.features.payouts` remains `true`.
    public function register(): void
    {
        $affiliate = $this->affiliateRegistrationService->register(
When `filament-affiliates.portal.registration_enabled` is `true`, the panel uses `PortalRegistration` as its registration page.

The registration form creates both:

- the authenticated user record, and
- an affiliate via `AffiliateRegistrationService`.

The form collects:

- name,
- email,
- password,
- affiliate/business name,
- optional website URL.

If affiliate owner mode is enabled, the registration flow uses the current owner context when creating the affiliate.
            programId: $this->program_id,
            referralCode: $this->referral_code,
use AIArmada\FilamentAffiliates\Pages\Portal\PortalRegistration;

class PortalRegistration extends FilamentRegister

    protected function createAffiliateForUser(Model $user, array $data): Affiliate
    }
        return app(AffiliateRegistrationService::class)->register([
            'name' => $data['affiliate_name'],
            'contact_email' => $data['email'],
            'website_url' => $data['website_url'] ?? null,
        ], $owner);
    }
}
```

The registration subheading changes with `affiliates.registration.approval_mode`:

- `auto` — the affiliate is immediately activated,
- `open` — the affiliate is created as pending,
- `admin` — the user is told their application will be reviewed.

If registration is disabled, the page redirects back to the portal login page.

## Authentication

The panel uses standard Filament auth middleware and the configured auth guard:

```php
'portal' => [
    'auth_guard' => 'web',
    'login_enabled' => true,
],
```

By default the panel requires an authenticated user, but it does not hard-block non-affiliate users with a special middleware. Portal pages use `InteractsWithAffiliate` and render empty-state behavior when no affiliate is linked to the current user.

If you want to enforce an affiliate-only gate, extend the panel provider and add your own auth middleware.

## Customizing Portal Access

You can extend the panel provider if you need stricter access rules or extra middleware:

```php
use AIArmada\FilamentAffiliates\AffiliatePanelProvider;
use Filament\Panel;

class AppAffiliatePanelProvider extends AffiliatePanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return parent::panel($panel)
            ->authMiddleware([
                \Filament\Http\Middleware\Authenticate::class,
                \App\Http\Middleware\EnsureUserIsAffiliate::class,
            ]);
    }
}
```

## Portal Theming

### Brand Colors

```php
'portal' => [
    'primary_color' => '#your-brand-color',
],
```

### Custom CSS

The provider already registers the bundled `affiliate-portal.css` asset. If you need more branding, extend the provider and register additional assets on the panel.

## Disabling Portal Features

Disable specific pages with the feature map:

```php
'portal' => [
    'features' => [
        'dashboard' => true,
        'links' => true,
        'conversions' => true,
        'payouts' => false,
    ],
],
```

Available feature keys are:

- `dashboard`
- `links`
- `conversions`
- `payouts`

## What the Portal Does Not Do by Default

The shipped portal does **not** currently provide:

- QR code generation,
- per-link analytics pages,
- custom login-page subclasses,
- special session-lifetime or single-session settings,
- webhook-specific portal features.

If you need those, extend the panel provider or add custom pages alongside the built-in portal pages.
### Brand Colors
