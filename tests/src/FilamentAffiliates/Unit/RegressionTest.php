<?php

declare(strict_types=1);

use AIArmada\Affiliates\Contracts\AffiliateLookup;
use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutEvent;
use AIArmada\Affiliates\Models\AffiliatePayoutMethod;
use AIArmada\Affiliates\Models\AffiliatePayoutOperation;
use AIArmada\Affiliates\Models\AffiliateUpline;
use AIArmada\Affiliates\Services\AffiliateReportService;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\FailedPayout;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\Authz\Models\Permission;
use AIArmada\Cart\Cart;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAffiliates\Actions\BulkPayoutAction;
use AIArmada\FilamentAffiliates\Actions\ResolveAffiliateLinkedOwner;
use AIArmada\FilamentAffiliates\Pages\ManageAffiliateCommissionSettings;
use AIArmada\FilamentAffiliates\Pages\PayoutBatchPage;
use AIArmada\FilamentAffiliates\Pages\Portal\PortalConversions;
use AIArmada\FilamentAffiliates\Pages\Portal\PortalLinks;
use AIArmada\FilamentAffiliates\Pages\Portal\PortalRegistration;
use AIArmada\FilamentAffiliates\Pages\ReportsPage;
use AIArmada\FilamentAffiliates\Resources\AffiliateFraudSignalResource;
use AIArmada\FilamentAffiliates\Services\PayoutExportService;
use AIArmada\FilamentAffiliates\Widgets\UplineVisualizationWidget;
use Filament\Actions\Enums\ActionStatus;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    config()->set('affiliates.owner.enabled', false);

    AffiliateUpline::query()->delete();
    AffiliateBalance::query()->delete();
    AffiliatePayoutEvent::query()->delete();
    AffiliatePayoutMethod::query()->delete();
    AffiliatePayoutOperation::query()->delete();
    AffiliatePayout::query()->delete();
    AffiliateConversion::query()->delete();
    Affiliate::query()->delete();
    User::query()->delete();
    Permission::query()->delete();
});

function createRepairAffiliate(array $overrides = []): Affiliate
{
    return Affiliate::create(array_merge([
        'code' => 'REPAIR-' . Str::uuid(),
        'name' => 'Repair Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ], $overrides));
}

function createRepairPayout(Affiliate $affiliate, array $overrides = []): AffiliatePayout
{
    $payout = AffiliatePayout::create(array_merge([
        'reference' => 'PAY-' . Str::uuid(),
        'status' => PendingPayout::class,
        'total_minor' => 5000,
        'currency' => 'USD',
        'payee_type' => $affiliate->getMorphClass(),
        'payee_id' => $affiliate->getKey(),
    ], $overrides));

    $operation = AffiliatePayoutOperation::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_payout_id' => $payout->getKey(),
        'operation_key' => 'repair:' . $payout->getKey(),
        'status' => 'reserved',
        'amount_minor' => $payout->total_minor,
        'currency' => $payout->currency,
        'claimed_at' => now(),
    ]);

    $payout->forceFill(['affiliate_payout_operation_id' => $operation->getKey()])->save();

    return $payout;
}

// Owner tuple is derived server-side from linked_user; tampered values stripped.
it('resolves affiliate owner from linked user and strips tampered owner input', function (): void {
    $user = User::create(['name' => 'Linked', 'email' => 'linked@example.com', 'password' => 'secret']);

    $resolved = ResolveAffiliateLinkedOwner::run([
        'name' => 'X',
        'linked_user' => (string) $user->getKey(),
        'owner_type' => 'App\\Models\\Admin',
        'owner_id' => 'spoofed',
    ]);

    expect($resolved['owner_type'])->toBe($user->getMorphClass())
        ->and($resolved['owner_id'])->toBe((string) $user->getKey())
        ->and($resolved)->not->toHaveKey('linked_user');
});

it('rejects unknown linked users and empty selections leave ownership untouched', function (): void {
    expect(fn () => ResolveAffiliateLinkedOwner::run(['linked_user' => (string) Str::uuid()]))
        ->toThrow(ValidationException::class);

    $resolved = ResolveAffiliateLinkedOwner::run(['name' => 'Y', 'linked_user' => '']);

    expect($resolved)->not->toHaveKey('owner_type')
        ->and($resolved)->not->toHaveKey('owner_id');
});

// Payout rejection routes through the domain action (funds/conversions synced).
it('rejects payouts through the domain action and returns conversions to approved', function (): void {
    $user = User::create(['name' => 'Rejector', 'email' => 'repair-rejector@example.com', 'password' => 'secret']);
    Permission::create(['name' => 'affiliates.payout.update', 'guard_name' => 'web']);
    $user->givePermissionTo('affiliates.payout.update');
    $this->actingAs($user);

    $affiliate = createRepairAffiliate();
    $payout = createRepairPayout($affiliate);

    $balance = AffiliateBalance::create([
        'affiliate_id' => $affiliate->getKey(),
        'currency' => 'USD',
        'available_minor' => 0,
        'minimum_payout_minor' => 0,
    ]);

    $payout->operation->forceFill(['payout_sequence' => 7])->save();

    $conversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'affiliate_payout_id' => $payout->getKey(),
        'external_reference' => 'REJ-001',
        'value_minor' => 10000,
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
        'status' => ApprovedConversion::class,
        'occurred_at' => now(),
    ]);

    $page = new PayoutBatchPage;
    $reject = $page->table(Table::make($page))->getAction('reject');
    $reject?->call(['record' => $payout, 'data' => ['reason' => 'Failed verification']]);

    $payout->refresh();
    $balance->refresh();
    $conversion->refresh();

    expect($payout->status)->toBeInstanceOf(FailedPayout::class)
        ->and($payout->failed_at)->not->toBeNull()
        ->and($payout->metadata['notes'] ?? null)->toBe('Failed verification')
        ->and($payout->events()->count())->toBeGreaterThanOrEqual(1)
        ->and($balance->available_minor)->toBe($payout->total_minor)
        ->and($conversion->affiliate_payout_id)->toBeNull();
});

// Commission settings page is authorized and validated.
it('gates commission settings behind affiliate management abilities', function (): void {
    $user = User::create(['name' => 'Guest', 'email' => 'repair-guest@example.com', 'password' => 'secret']);
    $this->actingAs($user);

    expect(ManageAffiliateCommissionSettings::canAccess())->toBeFalse();

    Permission::create(['name' => 'affiliate.update', 'guard_name' => 'web']);
    $user->givePermissionTo('affiliate.update');

    expect(ManageAffiliateCommissionSettings::canAccess())->toBeTrue();
});

it('validates commission rates and caps level rows', function (): void {
    $page = new ManageAffiliateCommissionSettings;
    $page->multi_level_rates = [['level' => 1, 'rate' => -5]];

    expect(fn () => $page->save())->toThrow(ValidationException::class);

    $page->multi_level_rates = [['level' => 1, 'rate' => 101]];

    expect(fn () => $page->save())->toThrow(ValidationException::class);

    config(['affiliates.upline.max_depth' => 2]);
    $page->multi_level_rates = [
        ['level' => 1, 'rate' => 10],
        ['level' => 2, 'rate' => 5],
    ];
    $page->addLevel();

    expect($page->multi_level_rates)->toHaveCount(2);
});

// Registration creates users from explicit fields only.
it('registers users from explicit fields and ignores stray input', function (): void {
    config(['affiliates.registration.enabled' => true]);

    app()->instance(AffiliateLookup::class, new class implements AffiliateLookup
    {
        public function findByCode(string $code): ?Affiliate
        {
            return null;
        }

        public function findByDefaultVoucherCode(string $voucherCode): ?Affiliate
        {
            return null;
        }

        public function findById(string $id): ?Affiliate
        {
            return null;
        }

        public function findActiveAffiliateByCookie(string $cookieValue): ?Affiliate
        {
            return null;
        }

        public function findActiveAttributionByCookie(string $cookieValue): ?AffiliateAttribution
        {
            return null;
        }

        public function findAttachedAttribution(Cart $cart): ?AffiliateAttribution
        {
            return null;
        }
    });

    $page = new PortalRegistration;
    $method = new ReflectionMethod(PortalRegistration::class, 'handleRegistration');
    $method->setAccessible(true);

    /** @var Model $user */
    $user = $method->invoke($page, [
        'name' => 'Stray Input',
        'email' => 'stray-input@example.com',
        'password' => bcrypt('password'),
        'affiliate_name' => 'Stray Affiliate',
        'phone' => '+60123456789',
        'is_admin' => true,
        'role' => 'super-admin',
    ]);

    expect($user->getAttribute('name'))->toBe('Stray Input')
        ->and($user->getAttribute('email'))->toBe('stray-input@example.com')
        ->and(Affiliate::query()->where('name', 'Stray Affiliate')->exists())->toBeTrue();
});

// Portal conversions use a single grouped whereIn.
it('lists own and descendant conversions without leaking other affiliates', function (): void {
    config(['affiliates.upline.enabled' => true]);

    $user = User::create(['name' => 'Conv', 'email' => 'repair-conv@example.com', 'password' => 'secret']);
    $this->actingAs($user);

    $affiliate = createRepairAffiliate();
    $affiliate->forceFill(['owner_type' => $user->getMorphClass(), 'owner_id' => (string) $user->getKey()])->save();

    $child = createRepairAffiliate(['code' => 'CHILD-' . Str::uuid()]);
    $stranger = createRepairAffiliate(['code' => 'STRANGER-' . Str::uuid()]);

    AffiliateUpline::create([
        'ancestor_id' => $affiliate->getKey(),
        'descendant_id' => $child->getKey(),
        'depth' => 1,
    ]);

    foreach ([$affiliate, $child, $stranger] as $index => $owner) {
        AffiliateConversion::create([
            'affiliate_id' => $owner->getKey(),
            'affiliate_code' => $owner->code,
            'external_reference' => 'RC-' . $index,
            'value_minor' => 1000,
            'commission_minor' => 100,
            'commission_currency' => 'USD',
            'status' => PendingConversion::class,
            'occurred_at' => now(),
        ]);
    }

    $page = new PortalConversions;
    $ids = $page->table(Table::make($page))->getQuery()->pluck('affiliate_id')->all();

    expect($ids)->toHaveCount(2)
        ->and($ids)->toContain($affiliate->getKey())
        ->and($ids)->toContain($child->getKey())
        ->and($ids)->not->toContain($stranger->getKey());
});

// Export sanitizes formula cells, escapes PDF meta, and sanitizes filenames.
it('sanitizes export cells and filenames against injection', function (): void {
    $affiliate = createRepairAffiliate(['code' => '=HYPERLINK("https://evil.example")']);
    $payout = createRepairPayout($affiliate, ['reference' => 'PAY/../../evil']);

    AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'affiliate_payout_id' => $payout->getKey(),
        'external_reference' => '=cmd|calc!A1',
        'value_minor' => 1000,
        'commission_minor' => 100,
        'commission_currency' => 'USD',
        'status' => ApprovedConversion::class,
        'occurred_at' => now(),
    ]);

    $service = new PayoutExportService;
    $response = $service->downloadCsv($payout);

    ob_start();
    $response->sendContent();
    $content = (string) ob_get_clean();

    expect($content)->toContain("'=HYPERLINK")
        ->and($content)->toContain("'=cmd|calc!A1")
        ->and($response->headers->get('Content-Disposition'))->toContain('PAY_evil.csv');
});

it('escapes payout references in exported HTML', function (): void {
    $affiliate = createRepairAffiliate();
    $payout = createRepairPayout($affiliate, ['reference' => 'PAY-<script>alert(1)</script>']);

    $service = new PayoutExportService;
    $method = new ReflectionMethod(PayoutExportService::class, 'buildPdfHtml');
    $method->setAccessible(true);

    $html = $method->invoke($service, $payout->refresh(), [['H1'], ['v1']]);

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->toContain('PAY-&lt;script&gt;');
});

// Bulk payout reports failure when every payout fails.
it('marks bulk payout runs as failed when every payout fails', function (): void {
    $user = User::create(['name' => 'Bulk', 'email' => 'repair-bulk@example.com', 'password' => 'secret']);
    Permission::create(['name' => 'affiliates.payout.update', 'guard_name' => 'web']);
    $this->actingAs($user);
    $user->givePermissionTo('affiliates.payout.update');

    $affiliate = createRepairAffiliate();
    $payout = createRepairPayout($affiliate);

    $action = BulkPayoutAction::make('bulk_process_payouts');
    $action->deselectRecordsAfterCompletion(false);
    $action->successNotification(null);
    $action->failureNotification(null);

    $action->call(['records' => new Collection([$payout])]);

    expect($action->getStatus())->toBe(ActionStatus::Failure);
});

// Link generation validates scheme and host server-side.
it('rejects off-host and non-http link targets', function (): void {
    $user = User::create(['name' => 'Links', 'email' => 'repair-links@example.com', 'password' => 'secret']);
    $this->actingAs($user);

    $affiliate = createRepairAffiliate();
    $affiliate->forceFill(['owner_type' => $user->getMorphClass(), 'owner_id' => (string) $user->getKey()])->save();

    $page = new PortalLinks;

    $page->targetUrl = 'javascript:alert(1)';

    expect(fn () => $page->generateLink())->toThrow(ValidationException::class);

    $page->targetUrl = 'https://evil.example/phish';
    $page->generatedLink = null;
    $page->generateLink();

    expect($page->generatedLink)->toBeNull();

    $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
    $scheme = (string) parse_url((string) config('app.url'), PHP_URL_SCHEME);
    $page->targetUrl = $scheme . '://shop.' . $host . '/item';
    $page->generateLink();

    expect($page->generatedLink)->toContain('aff=' . $affiliate->code);
});

// Upline widget clamps user-controlled depth.
it('clamps upline visualization depth to a safe bound', function (): void {
    $widget = new UplineVisualizationWidget;
    $widget->updatedDepth(99);

    expect($widget->depth)->toBe(UplineVisualizationWidget::MAX_DEPTH);

    $widget->updatedDepth(0);

    expect($widget->depth)->toBe(1);
});

// Clipboard handlers use @js and admin codes are alpha-dash.
it('uses js-safe clipboard handlers in portal blades', function (): void {
    $root = dirname(__DIR__, 4);

    foreach (['links', 'dashboard', 'vouchers'] as $view) {
        $source = (string) file_get_contents($root . '/packages/filament-affiliates/resources/views/pages/portal/' . $view . '.blade.php');

        expect($source)->not->toContain("writeText('{{")
            ->and($source)->toContain('writeText(@js(');
    }

    $form = (string) file_get_contents($root . '/packages/filament-affiliates/src/Resources/AffiliateResource/Schemas/AffiliateForm.php');

    expect($form)->toContain('->alphaDash()');
});

// Payout affiliate picker is lazy and owner-aware.
it('searches payout affiliates lazily instead of preloading every row', function (): void {
    $source = (string) file_get_contents(
        dirname(__DIR__, 4) . '/packages/filament-affiliates/src/Resources/AffiliatePayoutResource.php'
    );

    expect($source)->toContain('getSearchResultsUsing')
        ->and($source)->not->toContain('->preload()')
        ->and($source)->not->toContain('->options(fn (): array => Affiliate::query()');
});

// Malformed custom report dates fall back instead of throwing.
it('falls back to sane defaults for malformed custom report dates', function (): void {
    app()->instance(AffiliateReportService::class, new class
    {
        public function getSummary($startDate, $endDate): array
        {
            return [];
        }

        public function getTopAffiliates($startDate, $endDate, int $limit): array
        {
            return [];
        }

        public function getConversionTrend($startDate, $endDate): array
        {
            return [];
        }

        public function getTrafficSources($startDate, $endDate): array
        {
            return [];
        }
    });

    $page = new ReportsPage;
    $page->period = 'custom';
    $page->startDate = 'not-a-date';
    $page->endDate = 'also-bad';

    $page->generateReport();

    expect($page->reportData)->toHaveKeys(['summary', 'top_affiliates', 'conversion_trend', 'traffic_sources']);
});

it('orders swapped custom report dates instead of querying an empty range', function (): void {
    $captured = ['start' => null, 'end' => null];

    app()->instance(AffiliateReportService::class, new class($captured)
    {
        /** @var array{start:mixed, end:mixed} */
        public array $captured;

        public function __construct(array &$captured)
        {
            $this->captured = &$captured;
        }

        public function getSummary($startDate, $endDate): array
        {
            $this->captured['start'] = $startDate;
            $this->captured['end'] = $endDate;

            return [];
        }

        public function getTopAffiliates($startDate, $endDate, int $limit): array
        {
            return [];
        }

        public function getConversionTrend($startDate, $endDate): array
        {
            return [];
        }

        public function getTrafficSources($startDate, $endDate): array
        {
            return [];
        }
    });

    $page = new ReportsPage;
    $page->period = 'custom';
    $page->startDate = '2025-02-10';
    $page->endDate = '2025-01-05';
    $page->generateReport();

    expect($captured['start']->toDateString())->toBe('2025-01-05')
        ->and($captured['end']->toDateString())->toBe('2025-02-10');
});

// Fraud navigation badge is cached per owner.
it('caches the fraud signal navigation badge', function (): void {
    $affiliate = createRepairAffiliate();

    AffiliateFraudSignal::create([
        'affiliate_id' => $affiliate->getKey(),
        'rule_code' => 'velocity',
        'risk_points' => 80,
        'severity' => FraudSeverity::Critical,
        'description' => 'Velocity abuse detected',
        'status' => FraudSignalStatus::Detected,
        'detected_at' => now(),
    ]);

    expect(AffiliateFraudSignalResource::getNavigationBadge())->toBe('1');

    AffiliateFraudSignal::query()->delete();

    // Cached value survives the underlying delete within the TTL window.
    expect(AffiliateFraudSignalResource::getNavigationBadge())->toBe('1');
});
