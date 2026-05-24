# Affiliate Self-Service Portal

> **Document:** 06 of 11  
> **Package:** `aiarmada/affiliates`  
> **Status:** 🔴 0% Implemented  
> **Last Updated:** December 5, 2025

---

## Overview

Create a comprehensive **self-service affiliate portal** where affiliates can manage their accounts, access marketing materials, build tracking links, monitor performance, and request payouts—all without administrator intervention.

---

## Current State ⚠️

### Not Yet Implemented

This feature set is **entirely planned** — no portal code exists.

**Current Reality:**
- No affiliate-facing frontend
- Affiliates cannot self-register
- No link builder or creative access
- No performance dashboard for affiliates
- All management through admin Filament panel only

**Workarounds in Use:**
- Manual affiliate onboarding by admins
- External tools for link generation
- Email-based payout requests
- Spreadsheet sharing for performance data

---

## Vision Architecture

### Portal Structure

```
┌─────────────────────────────────────────────────────────────┐
│                    AFFILIATE PORTAL                          │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌─────────────────────────────────────────────────────┐    │
│  │  AUTHENTICATION                                      │    │
│  │  • Registration (with approval workflow)             │    │
│  │  • Login / Magic link                               │    │
│  │  • Password reset                                    │    │
│  │  • 2FA (optional)                                   │    │
│  └─────────────────────────────────────────────────────┘    │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐    │
│  │  DASHBOARD                                           │    │
│  │  • Real-time metrics (clicks, conversions, earnings) │    │
│  │  • Trend charts                                      │    │
│  │  • Recent activity feed                              │    │
│  │  • Notifications / announcements                     │    │
│  └─────────────────────────────────────────────────────┘    │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐    │
│  │  LINK BUILDER                                        │    │
│  │  • URL generator with UTM parameters                 │    │
│  │  • QR code generation                               │    │
│  │  • Short link creation                               │    │
│  │  • Deep link support                                │    │
│  └─────────────────────────────────────────────────────┘    │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐    │
│  │  CREATIVES LIBRARY                                   │    │
│  │  • Banners, text links, email templates              │    │
│  │  • Filtered by size, category                        │    │
│  │  • One-click copy embed code                         │    │
│  │  • Preview with affiliate code embedded              │    │
│  └─────────────────────────────────────────────────────┘    │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐    │
│  │  REPORTING                                           │    │
│  │  • Performance reports                               │    │
│  │  • Conversion details                                │    │
│  │  • Export (CSV, PDF)                                 │    │
│  │  • Custom date ranges                                │    │
│  └─────────────────────────────────────────────────────┘    │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐    │
│  │  PAYOUTS                                             │    │
│  │  • Balance overview                                  │    │
│  │  • Payout history                                    │    │
│  │  • Payout method management                          │    │
│  │  • Request on-demand payout                          │    │
│  └─────────────────────────────────────────────────────┘    │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐    │
│  │  PROFILE & SETTINGS                                  │    │
│  │  • Personal info                                     │    │
│  │  • Tax information (W-9, W-8BEN)                     │    │
│  │  • Notification preferences                          │    │
│  │  • API key management                                │    │
│  └─────────────────────────────────────────────────────┘    │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐    │
│  │  NETWORK (MLM)                                       │    │
│  │  • Downline overview                                 │    │
│  │  • Referral link for new affiliates                  │    │
│  │  • Team performance                                  │    │
│  │  • Override earnings breakdown                       │    │
│  └─────────────────────────────────────────────────────┘    │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐    │
│  │  SUPPORT & RESOURCES                                 │    │
│  │  • Help center / FAQ                                 │    │
│  │  • Ticket submission                                 │    │
│  │  • Program terms                                     │    │
│  │  • Training academy                                  │    │
│  └─────────────────────────────────────────────────────┘    │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Technology Options

### Option A: Livewire (Recommended)

Laravel Livewire with Volt for a seamless, reactive experience.

```php
// routes/affiliate.php
Route::middleware(['web', 'auth:affiliate'])->prefix('affiliate')->group(function () {
    Route::get('/', AffiliatePortalController::class)->name('affiliate.portal');
    Route::get('/dashboard', Dashboard::class)->name('affiliate.dashboard');
    Route::get('/links', LinkBuilder::class)->name('affiliate.links');
    Route::get('/creatives', CreativeLibrary::class)->name('affiliate.creatives');
    Route::get('/reports', Reports::class)->name('affiliate.reports');
    Route::get('/payouts', Payouts::class)->name('affiliate.payouts');
    Route::get('/profile', Profile::class)->name('affiliate.profile');
    Route::get('/network', NetworkOverview::class)->name('affiliate.network');
});
```

### Option B: Inertia + Vue/React

For teams preferring a JavaScript frontend.

### Option C: Filament Panels

Leverage Filament for the affiliate panel (separate from admin).

```php
class AffiliateFilamentPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('affiliate')
            ->path('affiliate')
            ->login()
            ->registration()
            ->authGuard('affiliate')
            ->colors(['primary' => Color::Indigo])
            ->discoverResources(in: app_path('Filament/Affiliate/Resources'))
            ->discoverPages(in: app_path('Filament/Affiliate/Pages'))
            ->discoverWidgets(in: app_path('Filament/Affiliate/Widgets'));
    }
}
```

---

## Proposed Models

### AffiliateLink (Custom Tracking Links)

```php
/**
 * @property string $id
 * @property string $affiliate_id
 * @property string $destination_url
 * @property string $tracking_url
 * @property string $short_url (optional)
 * @property string $custom_slug (optional)
 * @property string $campaign
 * @property string $sub_id, $sub_id_2, $sub_id_3
 * @property int $clicks
 * @property int $conversions
 * @property bool $is_active
 */
class AffiliateLink extends Model
{
    use HasUuids;
    
    public function affiliate(): BelongsTo;
    
    public function generateShortUrl(): string;
    public function generateQrCode(): string;
    public function getTrackingUrl(): string;
}
```

### AffiliateSupportTicket

```php
/**
 * @property string $id
 * @property string $affiliate_id
 * @property string $subject
 * @property string $category
 * @property string $status (open, pending, resolved, closed)
 * @property string $priority
 * @property string $assigned_to
 * @property Carbon $resolved_at
 */
class AffiliateSupportTicket extends Model
{
    use HasUuids;
    
    public function affiliate(): BelongsTo;
    public function messages(): HasMany;
}
```

---

## Portal Pages

### Dashboard

```php
class Dashboard extends Component
{
    public function mount(): void
    {
        $this->affiliate = auth('affiliate')->user();
    }
    
    public function render()
    {
        return view('affiliate.dashboard', [
            'stats' => $this->getStats(),
            'recentConversions' => $this->getRecentConversions(),
            'recentActivity' => $this->getRecentActivity(),
            'announcements' => $this->getAnnouncements(),
        ]);
    }
    
    private function getStats(): array
    {
        return [
            'clicks_today' => $this->affiliate->getClicksToday(),
            'conversions_today' => $this->affiliate->getConversionsToday(),
            'pending_earnings' => $this->affiliate->balance->holding_minor,
            'available_balance' => $this->affiliate->balance->available_minor,
            'conversion_rate' => $this->affiliate->getConversionRate(),
            'epc' => $this->affiliate->getEpc(),
        ];
    }
}
```

### Link Builder

```php
class LinkBuilder extends Component
{
    public ?string $destinationUrl = '';
    public ?string $campaign = '';
    public ?string $subId = '';
    
    public function generateLink(): void
    {
        $this->validate([
            'destinationUrl' => 'required|url',
        ]);
        
        $link = app(AffiliateLinkService::class)->create(
            affiliate: auth('affiliate')->user(),
            destinationUrl: $this->destinationUrl,
            campaign: $this->campaign,
            subId: $this->subId,
        );
        
        $this->dispatch('link-created', link: $link);
    }
    
    public function render()
    {
        return view('affiliate.link-builder', [
            'recentLinks' => auth('affiliate')->user()->links()->latest()->take(10)->get(),
        ]);
    }
}
```

### Payout Dashboard

```php
class Payouts extends Component
{
    public function requestPayout(): void
    {
        $affiliate = auth('affiliate')->user();
        
        if (!$affiliate->canRequestPayout()) {
            $this->addError('payout', 'Minimum balance not met or no verified payout method.');
            return;
        }
        
        app(AffiliatePayoutService::class)->requestOnDemandPayout($affiliate);
        
        $this->dispatch('payout-requested');
    }
    
    public function render()
    {
        $affiliate = auth('affiliate')->user();
        
        return view('affiliate.payouts', [
            'balance' => $affiliate->balance,
            'payoutMethods' => $affiliate->payoutMethods,
            'recentPayouts' => $affiliate->payouts()->latest()->take(10)->get(),
            'canRequestPayout' => $affiliate->canRequestPayout(),
        ]);
    }
}
```

---

## Services

### AffiliateLinkService

```php
class AffiliateLinkService
{
    public function create(
        Affiliate $affiliate,
        string $destinationUrl,
        ?string $campaign = null,
        ?string $subId = null,
        ?string $customSlug = null,
    ): AffiliateLink {
        $trackingUrl = $this->buildTrackingUrl(
            $destinationUrl,
            $affiliate->code,
            $campaign,
            $subId
        );
        
        return AffiliateLink::create([
            'affiliate_id' => $affiliate->id,
            'destination_url' => $destinationUrl,
            'tracking_url' => $trackingUrl,
            'short_url' => $this->generateShortUrl($trackingUrl),
            'custom_slug' => $customSlug,
            'campaign' => $campaign,
            'sub_id' => $subId,
        ]);
    }
    
    private function buildTrackingUrl(
        string $destination,
        string $affiliateCode,
        ?string $campaign,
        ?string $subId
    ): string {
        $params = [
            config('affiliates.tracking.parameter') => $affiliateCode,
        ];
        
        if ($campaign) {
            $params['utm_campaign'] = $campaign;
        }
        
        if ($subId) {
            $params['sub_id'] = $subId;
        }
        
        return $destination . '?' . http_build_query($params);
    }
    
    public function generateQrCode(AffiliateLink $link): string
    {
        return QrCode::size(300)->generate($link->short_url ?? $link->tracking_url);
    }
}
```

### AffiliateAuthService

```php
class AffiliateAuthService
{
    public function register(array $data): Affiliate
    {
        $affiliate = Affiliate::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'website_url' => $data['website_url'] ?? null,
            'status' => config('affiliates.registration.requires_approval')
                ? AffiliateStatus::Pending
                : AffiliateStatus::Active,
        ]);
        
        $affiliate->balance()->create([
            'currency' => config('affiliates.default_currency', 'USD'),
            'minimum_payout_minor' => config('affiliates.minimum_payout', 100_00),
        ]);
        
        event(new AffiliateRegistered($affiliate));
        
        return $affiliate;
    }
    
    public function sendMagicLink(string $email): void
    {
        $affiliate = Affiliate::where('email', $email)->first();
        
        if ($affiliate) {
            $token = $this->createMagicToken($affiliate);
            Mail::to($affiliate)->send(new MagicLinkMail($token));
        }
    }
}
```

---

## Database Schema

```php
// affiliate_links table
Schema::create('affiliate_links', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('affiliate_id');
    $table->string('destination_url');
    $table->string('tracking_url');
    $table->string('short_url')->nullable();
    $table->string('custom_slug')->nullable()->unique();
    $table->string('campaign')->nullable();
    $table->string('sub_id')->nullable();
    $table->string('sub_id_2')->nullable();
    $table->string('sub_id_3')->nullable();
    $table->unsignedBigInteger('clicks')->default(0);
    $table->unsignedBigInteger('conversions')->default(0);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    
    $table->index('affiliate_id');
    $table->index('custom_slug');
});

// affiliate_support_tickets table
Schema::create('affiliate_support_tickets', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('affiliate_id');
    $table->string('subject');
    $table->string('category');
    $table->string('status')->default('open');
    $table->string('priority')->default('normal');
    $table->foreignUuid('assigned_to')->nullable();
    $table->timestamp('resolved_at')->nullable();
    $table->timestamps();
    
    $table->index(['affiliate_id', 'status']);
});

// affiliate_ticket_messages table
Schema::create('affiliate_ticket_messages', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('ticket_id');
    $table->text('message');
    $table->boolean('is_from_affiliate')->default(true);
    $table->foreignUuid('user_id')->nullable();
    $table->json('attachments')->nullable();
    $table->timestamps();
    
    $table->index('ticket_id');
});
```

---

## Events

| Event | Trigger | Payload |
|-------|---------|---------|
| `AffiliateRegistered` | New affiliate registration | affiliate |
| `AffiliateApproved` | Admin approves affiliate | affiliate |
| `AffiliateLinkCreated` | New tracking link created | link |
| `PayoutRequested` | Affiliate requests payout | affiliate, amount |
| `SupportTicketCreated` | New support ticket | ticket |
| `SupportTicketResolved` | Ticket marked resolved | ticket |

---

## Configuration

```php
// config/affiliates.php
'portal' => [
    'enabled' => true,
    'path' => 'affiliate',
    
    'registration' => [
        'enabled' => true,
        'requires_approval' => true,
        'default_program_id' => null,
    ],
    
    'features' => [
        'link_builder' => true,
        'creatives_library' => true,
        'network_overview' => true, // MLM
        'on_demand_payout' => true,
        'support_tickets' => true,
        'training_academy' => false,
    ],
    
    'link_builder' => [
        'short_urls' => true,
        'qr_codes' => true,
        'deep_links' => false,
    ],
],
```

---

## Implementation Priority

| Phase | Deliverable | Effort | Status |
|-------|-------------|--------|--------|
| 1 | Portal authentication | 3 days | ⬜ Todo |
| 2 | Dashboard views | 3 days | ⬜ Todo |
| 3 | Link builder tool | 3 days | ⬜ Todo |
| 4 | AffiliateLink model | 2 days | ⬜ Todo |
| 5 | Creative library | 3 days | ⬜ Todo |
| 6 | Payout dashboard | 2 days | ⬜ Todo |
| 7 | Profile management | 2 days | ⬜ Todo |
| 8 | Network overview | 2 days | ⬜ Todo |
| 9 | Support ticket system | 3 days | ⬜ Todo |
| 10 | Training academy | 3 days | ⬜ Todo |

**Total Effort:** ~4 weeks

---

## Navigation

**Previous:** [05-analytics-reporting.md](05-analytics-reporting.md)  
**Next:** [07-payout-automation.md](07-payout-automation.md)
