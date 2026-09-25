---
title: Usage
---

# Usage

## Creating links

```php
use AIArmada\Links\Actions\CreateLink;

$link = CreateLink::run([
    'name' => 'Camera deal',
    'slug' => 'camera-deal', // optional: auto-generated when omitted
    'destination_url' => 'https://merchant.example/products/camera?aff_id=you',
    'utm_defaults' => [
        'utm_source' => 'newsletter',
        'utm_medium' => 'email',
    ],
    'max_clicks' => 1000, // optional
    'expires_at' => '2026-12-31 23:59:59', // optional
]);

$link->cloakedUrl(); // https://your-app.test/go/camera-deal
```

Slugs are globally unique, `alpha_dash`, at least 3 characters, and cannot use reserved words. Destinations must be valid URLs, `https` by default, without embedded credentials.

## Managing links

```php
use AIArmada\Links\Actions\DeactivateLink;
use AIArmada\Links\Actions\ReactivateLink;
use AIArmada\Links\Actions\UpdateLink;

UpdateLink::run($link, ['destination_url' => 'https://merchant.example/new-camera']);
DeactivateLink::run($link); // cloaked URL starts returning 410
ReactivateLink::run($link);
```

Deactivation uses a `deactivated_at` toggle timestamp; expiry uses `expires_at`; click limits use `max_clicks` against human clicks.

## The redirect

`GET /go/{slug}` resolves the link, records a click, merges parameters, and redirects:

- The link's `parameters` always win, so request query strings can never spoof them.
- Incoming `?utm_*` query parameters win over defaults.
- Incoming ad click IDs (`gclid`, `fbclid`, `msclkid`, `ttclid`, and friends) pass through untouched.
- The destination's own query parameters are preserved.
- The link's `utm_defaults` fill any remaining gaps.

Any other incoming query parameter is dropped: it is neither forwarded nor stored.

Unknown slugs return `404`. Deactivated, expired, limit-reached, or gate-blocked links return `410`. If click recording fails, the failure is reported and the visitor is still redirected.

Add rate limiting through `routing.middleware` when the redirect route is public (for example `['web', 'throttle:120,1']`).

## Recording clicks manually

Useful for emails or pages that link directly at the destination but should still count:

```php
use AIArmada\Links\Actions\RecordLinkClick;

RecordLinkClick::run($link, [
    'ip_address' => '203.0.113.10',
    'user_agent' => '...',
    'referrer' => 'https://newsletter.example/may',
    'utm_source' => 'newsletter',
    'properties' => ['campaign_id' => 'may-launch'],
]);
```

Missing values fall back to the current HTTP request when available. Returns `null` for bot visits when bot recording is disabled.

## Listening from other packages

Every lifecycle transition fires a domain event. Third-party integrations should listen, never query across the boundary:

```php
use AIArmada\Links\Events\LinkClicked;
use Illuminate\Support\Facades\Event;

Event::listen(LinkClicked::class, SendClickToAnalytics::class);
```

| Event | Fired when |
|---|---|
| `LinkCreated` / `LinkUpdated` | Link saved via actions. |
| `LinkDeactivated` / `LinkReactivated` | Lifecycle toggled (idempotent, no duplicate events). |
| `LinkClicked` | Click persisted (carries both `Link` and `LinkClick`). Signals ships an optional listener that records it as a `link.clicked` engagement event. |
| `LinkExpired` | Redirect attempted on an expired link. May fire repeatedly; listeners should dedupe. |
| `LinkClickLimitReached` | Redirect attempted on a maxed-out link. May fire repeatedly; listeners should dedupe. |

## Swapping implementations

Slug generation, bot detection, user-agent parsing, and redirect policy sit behind contracts:

```php
use AIArmada\Links\Contracts\BotDetectorInterface;

app()->bind(BotDetectorInterface::class, MyBotDetector::class);
```

## Consumer seams

Packages with their own domain objects (offers, campaigns) attach them as the link `subject`; clicks inherit it, and `forSubject($model)` scopes query both tables:

```php
CreateLink::run([
    'name' => 'Spring offer / AFF-1',
    'destination_url' => 'https://merchant.example/spring',
    'subject_type' => $offerLink->getMorphClass(),
    'subject_id' => (string) $offerLink->getKey(),
    'parameters' => ['anl' => $slug],
    'require_signature' => true,
]);
```

Redirect policy plugs in through `LinkGateInterface`: return a machine-readable reason to block with `410` plus a `LinkBlocked` event, or `null` to allow:

```php
use AIArmada\Links\Contracts\LinkGateInterface;
use AIArmada\Links\Models\Link;

final class OfferLinkGate implements LinkGateInterface
{
    public function blockedReason(Link $link): ?string
    {
        // Load the subject, check offer/site/approval state...
        return $blocked ? 'offer_inactive' : null;
    }
}
```

Signed URLs come from `GenerateLinkUrl`, which returns a plain cloaked URL unless the link requires a signature (TTL from `routing.signature_ttl_minutes`). Unsigned, tampered, or expired hits on signed links return `403` and record nothing.

## Multi-tenancy

Wrap management calls in explicit owner context:

```php
use AIArmada\CommerceSupport\Support\OwnerContext;

OwnerContext::withOwner($team, fn () => CreateLink::run([...]));
```

The public redirect route resolves slugs without owner context (slugs are global), then records the click under the link's own owner. See the [owner rules](../../commerce-support/docs/01-overview.md) for details.

## Pruning old clicks

```bash
php artisan links:prune-clicks
php artisan links:prune-clicks --days=90
```

## Read next

- [Troubleshooting](99-troubleshooting.md)
