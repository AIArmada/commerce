<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Enums\ProgramVisibility;
use AIArmada\Affiliates\Events\AffiliateCreated;
use AIArmada\Affiliates\Events\AffiliateProgramJoined;
use AIArmada\Affiliates\Events\FraudSignalDetected;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Models\AffiliateProgramTier;
use AIArmada\Affiliates\Services\ProgramService;
use AIArmada\Affiliates\States\Active;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Signals\Exceptions\CommerceSignalRecordingFailed;
use AIArmada\Signals\Listeners\RecordCommerceSignal;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\TrackedProperty;
use AIArmada\Signals\Services\CommerceSignalsRecorder;
use AIArmada\Signals\Support\CommerceSignalsIntegrationRegistrar;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(SignalsTestCase::class);

beforeEach(function (): void {
    Schema::dropIfExists('affiliate_fraud_signals');
    Schema::dropIfExists('affiliate_program_memberships');
    Schema::dropIfExists('affiliate_program_tiers');
    Schema::dropIfExists('affiliate_programs');
    Schema::dropIfExists('affiliates');

    Schema::create('affiliates', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('code')->unique();
        $table->string('handle', 40)->unique();
        $table->string('name');
        $table->string('status')->default(Active::class);
        $table->string('registration_approval_mode', 16)->default('admin');
        $table->string('commission_type')->default(CommissionType::Percentage->value);
        $table->unsignedInteger('commission_rate')->default(1000);
        $table->string('currency', 3)->default('MYR');
        $table->string('default_voucher_code')->nullable();
        $table->json('metadata')->nullable();
        $table->nullableUuidMorphs('owner');
        $table->timestamps();
    });

    Schema::create('affiliate_programs', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('slug');
        $table->string('status')->default('draft');
        $table->string('visibility', 32)->default('private');
        $table->boolean('requires_approval')->default(true);
        $table->nullableUuidMorphs('owner');
        $table->json('eligibility_rules')->nullable();
        $table->timestamps();
    });

    Schema::create('affiliate_program_tiers', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->foreignUuid('program_id');
        $table->string('name');
        $table->integer('level');
        $table->integer('commission_rate_basis_points');
        $table->integer('min_conversions')->default(0);
        $table->integer('min_revenue')->default(0);
        $table->string('min_revenue_currency', 3)->default('MYR');
        $table->timestamps();
    });

    Schema::create('affiliate_program_memberships', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->foreignUuid('affiliate_id');
        $table->foreignUuid('program_id');
        $table->foreignUuid('tier_id')->nullable();
        $table->string('status')->default('pending');
        $table->timestamp('applied_at');
        $table->timestamp('approved_at')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->foreignUuid('approved_by')->nullable();
        $table->timestamp('rejected_at')->nullable();
        $table->timestamp('suspended_at')->nullable();
        $table->timestamps();
    });

    Schema::create('affiliate_fraud_signals', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->foreignUuid('affiliate_id');
        $table->foreignUuid('conversion_id')->nullable();
        $table->foreignUuid('touchpoint_id')->nullable();
        $table->string('rule_code', 50);
        $table->integer('risk_points');
        $table->string('severity', 20);
        $table->string('description');
        $table->json('evidence')->nullable();
        $table->string('status', 20)->default('detected');
        $table->timestamp('detected_at');
        $table->timestamp('reviewed_at')->nullable();
        $table->foreignUuid('reviewed_by')->nullable();
        $table->timestamp('dismissed_at')->nullable();
        $table->timestamp('confirmed_at')->nullable();
        $table->timestamps();
    });
});

function affiliateLifecycleProperty(string $slug, string $name): TrackedProperty
{
    return TrackedProperty::query()->create([
        'name' => $name,
        'slug' => $slug,
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);
}

function affiliateLifecycleAffiliate(Model $owner, string $code): Affiliate
{
    // Tenant fixtures are born owned inside their owner context: no
    // transient global row, no post-create reassignment.
    return OwnerContext::withOwner($owner, static function () use ($owner, $code): Affiliate {
        $affiliate = new Affiliate([
            'code' => $code,
            'name' => 'Lifecycle ' . $code,
            'status' => Active::class,
            'commission_type' => CommissionType::Percentage->value,
            'commission_rate' => 1000,
            'currency' => 'MYR',
        ]);
        $affiliate->owner_type = $owner->getMorphClass();
        $affiliate->owner_id = $owner->getKey();
        $affiliate->save();

        return $affiliate->fresh() ?? $affiliate;
    });
}

function affiliateLifecycleMembership(Affiliate $affiliate, AffiliateProgram $program): AffiliateProgramMembership
{
    return AffiliateProgramMembership::query()->create([
        'affiliate_id' => $affiliate->getKey(),
        'program_id' => $program->getKey(),
        'status' => MembershipStatus::Approved,
        'applied_at' => now()->subDay(),
        'approved_at' => now(),
    ]);
}

it('records an affiliate created signal without visitor identity or PII', function (): void {
    Event::listen(AffiliateCreated::class, RecordCommerceSignal::class);

    $owner = User::query()->firstOrFail();
    $property = affiliateLifecycleProperty('lifecycle-created-property', 'Lifecycle Created Property');
    $affiliate = affiliateLifecycleAffiliate($owner, 'LIFECYCLE-ALI');

    Event::dispatch(new AffiliateCreated($affiliate));

    $event = SignalEvent::query()->withoutOwnerScope()->sole();

    expect($event->tracked_property_id)->toBe($property->id)
        ->and($event->event_name)->toBe('affiliate.created')
        ->and($event->event_category)->toBe('affiliate_lifecycle')
        ->and($event->occurred_at->timestamp)->toBe($affiliate->created_at->timestamp)
        ->and($event->idempotency_key)->toBe('affiliate-created:' . $affiliate->getKey())
        ->and($event->source_event_id)->toBe((string) $affiliate->getKey())
        ->and($event->revenue_minor)->toBe(0)
        ->and($event->signal_session_id)->toBeNull()
        ->and($event->signal_identity_id)->toBeNull()
        ->and($event->path)->toBeNull()
        ->and($event->url)->toBeNull()
        ->and($event->referrer)->toBeNull()
        ->and($event->source)->toBeNull()
        ->and($event->medium)->toBeNull()
        ->and($event->campaign)->toBeNull()
        ->and($event->content)->toBeNull()
        ->and($event->term)->toBeNull()
        ->and($event->properties)->toBe([
            'affiliate_id' => (string) $affiliate->getKey(),
            'affiliate_code' => 'LIFECYCLE-ALI',
            'registration_approval_mode' => 'admin',
        ]);
});

it('deduplicates repeated affiliate created dispatches', function (): void {
    Event::listen(AffiliateCreated::class, RecordCommerceSignal::class);

    $owner = User::query()->firstOrFail();
    affiliateLifecycleProperty('lifecycle-created-dupe-property', 'Lifecycle Created Dupe Property');
    $affiliate = affiliateLifecycleAffiliate($owner, 'DUPE-ALI');

    Event::dispatch(new AffiliateCreated($affiliate));
    Event::dispatch(new AffiliateCreated($affiliate));

    expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(1);
});

it('records nothing for affiliate created without a matching property', function (): void {
    Event::listen(AffiliateCreated::class, RecordCommerceSignal::class);

    $owner = User::query()->firstOrFail();
    $affiliate = affiliateLifecycleAffiliate($owner, 'NOPROP-ALI');

    Event::dispatch(new AffiliateCreated($affiliate));

    expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0)
        ->and(app(CommerceSignalsRecorder::class)->recordAffiliateCreated($affiliate))->toBeNull();
});

it('routes affiliate created signals to the affiliate owner property', function (): void {
    config()->set('affiliates.owner.enabled', true);

    Event::listen(AffiliateCreated::class, RecordCommerceSignal::class);

    $ownerB = User::query()->create([
        'name' => 'Lifecycle Owner B',
        'email' => 'lifecycle-owner-b@example.com',
        'password' => 'secret',
    ]);

    affiliateLifecycleProperty('lifecycle-owner-a-property', 'Lifecycle Owner A Property');

    $propertyB = OwnerContext::withOwner($ownerB, static fn (): TrackedProperty => affiliateLifecycleProperty('lifecycle-owner-b-property', 'Lifecycle Owner B Property'));

    $affiliate = affiliateLifecycleAffiliate($ownerB, 'OWNERB-ALI');

    Event::dispatch(new AffiliateCreated($affiliate));

    expect(SignalEvent::query()->withoutOwnerScope()->sole()->tracked_property_id)->toBe($propertyB->id);
});

it('records an affiliate program joined signal keyed by membership', function (): void {
    Event::listen(AffiliateProgramJoined::class, RecordCommerceSignal::class);

    $owner = User::query()->firstOrFail();
    $property = affiliateLifecycleProperty('lifecycle-joined-property', 'Lifecycle Joined Property');
    $affiliate = affiliateLifecycleAffiliate($owner, 'JOINED-ALI');

    $program = AffiliateProgram::query()->create([
        'name' => 'Ramadan Program',
        'slug' => 'ramadan-program',
    ]);

    $membership = affiliateLifecycleMembership($affiliate, $program);

    Event::dispatch(new AffiliateProgramJoined($affiliate, $program, $membership));

    $event = SignalEvent::query()->withoutOwnerScope()->sole();

    expect($event->tracked_property_id)->toBe($property->id)
        ->and($event->event_name)->toBe('affiliate.program.joined')
        ->and($event->event_category)->toBe('affiliate_lifecycle')
        ->and($event->occurred_at->timestamp)->toBe($membership->approved_at->timestamp)
        ->and($event->idempotency_key)->toBe('affiliate-program-joined:' . $membership->getKey())
        ->and($event->source_event_id)->toBe((string) $membership->getKey())
        ->and($event->revenue_minor)->toBe(0)
        ->and($event->signal_session_id)->toBeNull()
        ->and($event->signal_identity_id)->toBeNull()
        ->and($event->path)->toBeNull()
        ->and($event->url)->toBeNull()
        ->and($event->referrer)->toBeNull()
        ->and($event->source)->toBeNull()
        ->and($event->medium)->toBeNull()
        ->and($event->campaign)->toBeNull()
        ->and($event->content)->toBeNull()
        ->and($event->term)->toBeNull()
        ->and($event->properties)->toBe([
            'membership_id' => (string) $membership->getKey(),
            'affiliate_id' => (string) $affiliate->getKey(),
            'affiliate_code' => 'JOINED-ALI',
            'program_id' => (string) $program->getKey(),
        ]);
});

it('counts one join per membership on repeated approval dispatches', function (): void {
    Event::listen(AffiliateProgramJoined::class, RecordCommerceSignal::class);

    $owner = User::query()->firstOrFail();
    affiliateLifecycleProperty('lifecycle-joined-dupe-property', 'Lifecycle Joined Dupe Property');
    $affiliate = affiliateLifecycleAffiliate($owner, 'REJOIN-ALI');

    $program = AffiliateProgram::query()->create([
        'name' => 'Evergreen Program',
        'slug' => 'evergreen-program',
    ]);

    $membership = affiliateLifecycleMembership($affiliate, $program);

    Event::dispatch(new AffiliateProgramJoined($affiliate, $program, $membership));
    Event::dispatch(new AffiliateProgramJoined($affiliate, $program, $membership));

    expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(1);
});

it('rejects program joined calls with mismatched references', function (): void {
    $owner = User::query()->firstOrFail();
    affiliateLifecycleProperty('lifecycle-joined-mismatch-property', 'Lifecycle Joined Mismatch Property');
    $affiliate = affiliateLifecycleAffiliate($owner, 'MISMATCH-ALI');
    $otherAffiliate = affiliateLifecycleAffiliate($owner, 'MISMATCH-OTHER');

    $program = AffiliateProgram::query()->create([
        'name' => 'Mismatch Program',
        'slug' => 'mismatch-program',
    ]);

    $membership = affiliateLifecycleMembership($affiliate, $program);

    expect(fn (): ?SignalEvent => app(CommerceSignalsRecorder::class)->recordAffiliateProgramJoined($otherAffiliate, $program, $membership))
        ->toThrow(InvalidArgumentException::class, 'does not match the canonical source');
});

it('records a sanitized fraud detected signal on model creation', function (): void {
    Event::listen(FraudSignalDetected::class, RecordCommerceSignal::class);

    $owner = User::query()->firstOrFail();
    $property = affiliateLifecycleProperty('lifecycle-fraud-property', 'Lifecycle Fraud Property');
    $affiliate = affiliateLifecycleAffiliate($owner, 'FRAUD-ALI');

    $conversionId = (string) Str::uuid();
    $touchpointId = (string) Str::uuid();

    $signal = AffiliateFraudSignal::query()->create([
        'affiliate_id' => $affiliate->getKey(),
        'conversion_id' => $conversionId,
        'touchpoint_id' => $touchpointId,
        'rule_code' => 'velocity',
        'risk_points' => 85,
        'severity' => FraudSeverity::High,
        'description' => 'Too many conversions from one device fingerprint',
        'evidence' => ['device' => 'fp-123', 'ip' => '203.0.113.9'],
        'detected_at' => now(),
    ]);

    $event = SignalEvent::query()->withoutOwnerScope()->sole();

    expect($event->tracked_property_id)->toBe($property->id)
        ->and($event->event_name)->toBe('affiliate.fraud.detected')
        ->and($event->event_category)->toBe('affiliate_risk')
        ->and($event->occurred_at->timestamp)->toBe($signal->detected_at->timestamp)
        ->and($event->idempotency_key)->toBe('affiliate-fraud-detected:' . $signal->getKey())
        ->and($event->source_event_id)->toBe((string) $signal->getKey())
        ->and($event->revenue_minor)->toBe(0)
        ->and($event->signal_session_id)->toBeNull()
        ->and($event->signal_identity_id)->toBeNull()
        ->and($event->path)->toBeNull()
        ->and($event->url)->toBeNull()
        ->and($event->referrer)->toBeNull()
        ->and($event->source)->toBeNull()
        ->and($event->medium)->toBeNull()
        ->and($event->campaign)->toBeNull()
        ->and($event->content)->toBeNull()
        ->and($event->term)->toBeNull()
        ->and($event->properties)->toBe([
            'fraud_signal_id' => (string) $signal->getKey(),
            'affiliate_id' => (string) $affiliate->getKey(),
            'conversion_id' => $conversionId,
            'touchpoint_id' => $touchpointId,
            'rule_code' => 'velocity',
            'severity' => 'high',
            'risk_points' => 85,
        ]);
});

it('deduplicates repeated fraud detected dispatches', function (): void {
    Event::listen(FraudSignalDetected::class, RecordCommerceSignal::class);

    $owner = User::query()->firstOrFail();
    affiliateLifecycleProperty('lifecycle-fraud-dupe-property', 'Lifecycle Fraud Dupe Property');
    $affiliate = affiliateLifecycleAffiliate($owner, 'FRAUD-DUPE-ALI');

    $signal = AffiliateFraudSignal::query()->create([
        'affiliate_id' => $affiliate->getKey(),
        'rule_code' => 'velocity',
        'risk_points' => 40,
        'severity' => FraudSeverity::Low,
        'description' => 'Mild velocity anomaly',
        'detected_at' => now(),
    ]);

    Event::dispatch(new FraudSignalDetected($signal));

    expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(1);
});

it('derives fraud signal ownership from the affiliate', function (): void {
    config()->set('affiliates.owner.enabled', true);

    Event::listen(FraudSignalDetected::class, RecordCommerceSignal::class);

    $ownerB = User::query()->create([
        'name' => 'Fraud Owner B',
        'email' => 'fraud-owner-b@example.com',
        'password' => 'secret',
    ]);

    affiliateLifecycleProperty('lifecycle-fraud-a-property', 'Lifecycle Fraud A Property');

    $propertyB = OwnerContext::withOwner($ownerB, static fn (): TrackedProperty => affiliateLifecycleProperty('lifecycle-fraud-b-property', 'Lifecycle Fraud B Property'));

    $affiliate = affiliateLifecycleAffiliate($ownerB, 'FRAUD-OWNERB-ALI');

    OwnerContext::withOwner($ownerB, static function () use ($affiliate): void {
        AffiliateFraudSignal::query()->create([
            'affiliate_id' => $affiliate->getKey(),
            'rule_code' => 'duplicate_payout',
            'risk_points' => 120,
            'severity' => FraudSeverity::Critical,
            'description' => 'Duplicate payout attempt',
            'detected_at' => now(),
        ]);
    });

    expect(SignalEvent::query()->withoutOwnerScope()->sole()->tracked_property_id)->toBe($propertyB->id);
});

it('honours custom event names and categories for lifecycle outputs', function (): void {
    Event::listen(AffiliateCreated::class, RecordCommerceSignal::class);
    Event::listen(AffiliateProgramJoined::class, RecordCommerceSignal::class);
    Event::listen(FraudSignalDetected::class, RecordCommerceSignal::class);

    config()->set('signals.integrations.affiliates.created_event_name', 'custom.affiliate.created');
    config()->set('signals.integrations.affiliates.program_joined_event_name', 'custom.program.joined');
    config()->set('signals.integrations.affiliates.lifecycle_event_category', 'custom_lifecycle');
    config()->set('signals.integrations.affiliates.fraud_detected_event_name', 'custom.fraud.detected');
    config()->set('signals.integrations.affiliates.fraud_event_category', 'custom_risk');

    $owner = User::query()->firstOrFail();
    affiliateLifecycleProperty('lifecycle-custom-property', 'Lifecycle Custom Property');
    $affiliate = affiliateLifecycleAffiliate($owner, 'CUSTOM-ALI');

    $program = AffiliateProgram::query()->create([
        'name' => 'Custom Program',
        'slug' => 'custom-program',
    ]);

    $membership = affiliateLifecycleMembership($affiliate, $program);

    Event::dispatch(new AffiliateCreated($affiliate));
    Event::dispatch(new AffiliateProgramJoined($affiliate, $program, $membership));

    AffiliateFraudSignal::query()->create([
        'affiliate_id' => $affiliate->getKey(),
        'rule_code' => 'velocity',
        'risk_points' => 10,
        'severity' => FraudSeverity::Low,
        'description' => 'Custom names fraud probe',
        'detected_at' => now(),
    ]);

    $events = SignalEvent::query()->withoutOwnerScope()->orderBy('event_name')->get();

    expect($events)->toHaveCount(3)
        ->and($events->pluck('event_name')->all())->toBe([
            'custom.affiliate.created',
            'custom.fraud.detected',
            'custom.program.joined',
        ])
        ->and($events->pluck('event_category')->unique()->sort()->values()->all())->toBe([
            'custom_lifecycle',
            'custom_risk',
        ]);
});

it('rejects wrong-model sources on the strict recorder surface', function (): void {
    $owner = User::query()->firstOrFail();
    $property = affiliateLifecycleProperty('lifecycle-strict-property', 'Lifecycle Strict Property');
    $affiliate = affiliateLifecycleAffiliate($owner, 'STRICT-ALI');

    $recorder = app(CommerceSignalsRecorder::class);

    expect(fn (): ?SignalEvent => $recorder->recordAffiliateCreated($property))
        ->toThrow(InvalidArgumentException::class, 'must be an instance of')
        ->and(fn (): ?SignalEvent => $recorder->recordAffiliateFraudSignalDetected($affiliate))
        ->toThrow(InvalidArgumentException::class, 'must be an instance of');
});

it('leaves lifecycle listeners unwired by default', function (): void {
    $owner = User::query()->firstOrFail();
    affiliateLifecycleProperty('lifecycle-default-off-property', 'Lifecycle Default Off Property');
    $affiliate = affiliateLifecycleAffiliate($owner, 'DEFAULTOFF-ALI');

    $program = AffiliateProgram::query()->create([
        'name' => 'Default Off Program',
        'slug' => 'default-off-program',
    ]);

    $membership = affiliateLifecycleMembership($affiliate, $program);

    Event::dispatch(new AffiliateCreated($affiliate));
    Event::dispatch(new AffiliateProgramJoined($affiliate, $program, $membership));

    AffiliateFraudSignal::query()->create([
        'affiliate_id' => $affiliate->getKey(),
        'rule_code' => 'velocity',
        'risk_points' => 10,
        'severity' => FraudSeverity::Low,
        'description' => 'Default off fraud probe',
        'detected_at' => now(),
    ]);

    expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0);
});

it('wires lifecycle listeners when explicitly enabled', function (): void {
    config()->set('signals.integrations.affiliates.listen_for_created', true);
    config()->set('signals.integrations.affiliates.listen_for_program_joined', true);
    config()->set('signals.integrations.affiliates.listen_for_fraud_detected', true);

    app(CommerceSignalsIntegrationRegistrar::class)->boot();

    $owner = User::query()->firstOrFail();
    affiliateLifecycleProperty('lifecycle-wired-property', 'Lifecycle Wired Property');
    $affiliate = affiliateLifecycleAffiliate($owner, 'WIRED-ALI');

    $program = AffiliateProgram::query()->create([
        'name' => 'Wired Program',
        'slug' => 'wired-program',
    ]);

    $membership = affiliateLifecycleMembership($affiliate, $program);

    Event::dispatch(new AffiliateCreated($affiliate));
    Event::dispatch(new AffiliateProgramJoined($affiliate, $program, $membership));

    AffiliateFraudSignal::query()->create([
        'affiliate_id' => $affiliate->getKey(),
        'rule_code' => 'velocity',
        'risk_points' => 10,
        'severity' => FraudSeverity::Low,
        'description' => 'Wired fraud probe',
        'detected_at' => now(),
    ]);

    expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(3);
});

it('records joins through the real program service paths', function (): void {
    Event::listen(AffiliateProgramJoined::class, RecordCommerceSignal::class);

    $owner = User::query()->firstOrFail();
    affiliateLifecycleProperty('lifecycle-service-join-property', 'Lifecycle Service Join Property');
    $affiliate = affiliateLifecycleAffiliate($owner, 'SERVICE-JOIN-ALI');

    $openProgram = AffiliateProgram::query()->create([
        'name' => 'Open Service Program',
        'slug' => 'open-service-program',
        'status' => ProgramStatus::Active,
        'visibility' => ProgramVisibility::Public,
        'requires_approval' => false,
    ]);

    $membership = app(ProgramService::class)->joinProgram($affiliate, $openProgram);

    expect(SignalEvent::query()->withoutOwnerScope()->sole()->source_event_id)->toBe((string) $membership->getKey());
});

it('records pending approvals only when approved', function (): void {
    Event::listen(AffiliateProgramJoined::class, RecordCommerceSignal::class);

    $owner = User::query()->firstOrFail();
    affiliateLifecycleProperty('lifecycle-pending-join-property', 'Lifecycle Pending Join Property');
    $affiliate = affiliateLifecycleAffiliate($owner, 'PENDING-JOIN-ALI');

    $program = AffiliateProgram::query()->create([
        'name' => 'Approval Service Program',
        'slug' => 'approval-service-program',
        'status' => ProgramStatus::Active,
        'visibility' => ProgramVisibility::Public,
        'requires_approval' => true,
    ]);

    $service = app(ProgramService::class);
    $membership = $service->joinProgram($affiliate, $program);

    expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0);

    $service->approveMembership($membership);
    $service->approveMembership($membership->fresh() ?? $membership);

    expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(1)
        ->and(SignalEvent::query()->withoutOwnerScope()->sole()->source_event_id)->toBe((string) $membership->getKey());
});

it('records a new join with a new identity after leave and rejoin', function (): void {
    Event::listen(AffiliateProgramJoined::class, RecordCommerceSignal::class);

    $owner = User::query()->firstOrFail();
    affiliateLifecycleProperty('lifecycle-rejoin-property', 'Lifecycle Rejoin Property');
    $affiliate = affiliateLifecycleAffiliate($owner, 'REJOIN-SERVICE-ALI');

    $program = AffiliateProgram::query()->create([
        'name' => 'Rejoin Service Program',
        'slug' => 'rejoin-service-program',
        'status' => ProgramStatus::Active,
        'visibility' => ProgramVisibility::Public,
        'requires_approval' => false,
    ]);

    $service = app(ProgramService::class);
    $first = $service->joinProgram($affiliate, $program);
    $service->leaveProgram($affiliate, $program);
    $second = $service->joinProgram($affiliate, $program);

    $sourceIds = SignalEvent::query()->withoutOwnerScope()->pluck('source_event_id')->all();
    sort($sourceIds);

    $expected = [(string) $first->getKey(), (string) $second->getKey()];
    sort($expected);

    expect($second->getKey())->not->toBe($first->getKey())
        ->and($sourceIds)->toBe($expected);
});

it('records tier assignments on program joins', function (): void {
    Event::listen(AffiliateProgramJoined::class, RecordCommerceSignal::class);

    $owner = User::query()->firstOrFail();
    affiliateLifecycleProperty('lifecycle-tier-property', 'Lifecycle Tier Property');
    $affiliate = affiliateLifecycleAffiliate($owner, 'TIER-ALI');

    $program = AffiliateProgram::query()->create([
        'name' => 'Tiered Program',
        'slug' => 'tiered-program',
    ]);

    $tier = AffiliateProgramTier::query()->create([
        'program_id' => $program->getKey(),
        'name' => 'Gold',
        'level' => 1,
        'commission_rate_basis_points' => 1500,
    ]);

    $membership = AffiliateProgramMembership::query()->create([
        'affiliate_id' => $affiliate->getKey(),
        'program_id' => $program->getKey(),
        'tier_id' => $tier->getKey(),
        'status' => MembershipStatus::Approved,
        'applied_at' => now()->subDay(),
        'approved_at' => now(),
    ]);

    Event::dispatch(new AffiliateProgramJoined($affiliate, $program, $membership));

    expect(SignalEvent::query()->withoutOwnerScope()->sole()->properties)->toMatchArray([
        'tier_id' => (string) $tier->getKey(),
    ]);
});

it('emits fraud signals only after the creating transaction commits', function (): void {
    Event::listen(FraudSignalDetected::class, RecordCommerceSignal::class);

    $owner = User::query()->firstOrFail();
    affiliateLifecycleProperty('lifecycle-fraud-commit-property', 'Lifecycle Fraud Commit Property');
    $affiliate = affiliateLifecycleAffiliate($owner, 'FRAUD-COMMIT-ALI');

    DB::transaction(function () use ($affiliate): void {
        AffiliateFraudSignal::query()->create([
            'affiliate_id' => $affiliate->getKey(),
            'rule_code' => 'velocity',
            'risk_points' => 30,
            'severity' => FraudSeverity::Low,
            'description' => 'Commit probe',
            'detected_at' => now(),
        ]);

        expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0);
    });

    expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(1);
});

it('emits no fraud signal when the creating transaction rolls back', function (): void {
    Event::listen(FraudSignalDetected::class, RecordCommerceSignal::class);

    $owner = User::query()->firstOrFail();
    affiliateLifecycleProperty('lifecycle-fraud-rollback-property', 'Lifecycle Fraud Rollback Property');
    $affiliate = affiliateLifecycleAffiliate($owner, 'FRAUD-ROLLBACK-ALI');

    try {
        DB::transaction(function () use ($affiliate): void {
            AffiliateFraudSignal::query()->create([
                'affiliate_id' => $affiliate->getKey(),
                'rule_code' => 'velocity',
                'risk_points' => 30,
                'severity' => FraudSeverity::Low,
                'description' => 'Rollback probe',
                'detected_at' => now(),
            ]);

            throw new RuntimeException('fraud rollback probe');
        });

        $this->fail('The creating transaction should have rolled back.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('fraud rollback probe');
    }

    expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0);
});

it('routes fraud signals under affiliates enforcement after context restoration', function (): void {
    config()->set('affiliates.owner.enabled', true);

    Event::listen(FraudSignalDetected::class, RecordCommerceSignal::class);

    $ownerB = User::query()->create([
        'name' => 'Enforcement Owner B',
        'email' => 'enforcement-owner-b@example.com',
        'password' => 'secret',
    ]);

    $propertyB = OwnerContext::withOwner($ownerB, static fn (): TrackedProperty => affiliateLifecycleProperty('lifecycle-enforcement-b-property', 'Lifecycle Enforcement B Property'));

    $affiliate = OwnerContext::withOwner($ownerB, static function () use ($ownerB): Affiliate {
        $created = Affiliate::query()->create([
            'code' => 'ENFORCEMENT-ALI',
            'name' => 'Enforcement Ali',
            'status' => Active::class,
            'commission_type' => CommissionType::Percentage->value,
            'commission_rate' => 1000,
            'currency' => 'MYR',
        ]);
        $created->forceFill([
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => $ownerB->getKey(),
        ])->save();

        return $created->fresh() ?? $created;
    });

    DB::transaction(function () use ($ownerB, $affiliate): void {
        OwnerContext::withOwner($ownerB, static function () use ($affiliate): void {
            AffiliateFraudSignal::query()->create([
                'affiliate_id' => $affiliate->getKey(),
                'rule_code' => 'duplicate_payout',
                'risk_points' => 120,
                'severity' => FraudSeverity::Critical,
                'description' => 'Enforcement probe',
                'detected_at' => now(),
            ]);
        });
    });

    expect(SignalEvent::query()->withoutOwnerScope()->sole()->tracked_property_id)->toBe($propertyB->id);
});

it('records global affiliates on the global property', function (): void {
    Event::listen(AffiliateCreated::class, RecordCommerceSignal::class);

    $property = OwnerContext::withOwner(null, static fn (): TrackedProperty => affiliateLifecycleProperty('lifecycle-global-property', 'Lifecycle Global Property'));

    $affiliate = Affiliate::query()->create([
        'code' => 'GLOBAL-ALI',
        'name' => 'Global Ali',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage->value,
        'commission_rate' => 1000,
        'currency' => 'MYR',
    ]);

    Event::dispatch(new AffiliateCreated($affiliate));

    expect(SignalEvent::query()->withoutOwnerScope()->sole()->tracked_property_id)->toBe($property->id);
});

it('rejects half-null owner tuples instead of routing globally', function (): void {
    app()->instance(ExceptionHandler::class, new class implements ExceptionHandler
    {
        /** @var list<Throwable> */
        public array $reported = [];

        public function report(Throwable $e)
        {
            $this->reported[] = $e;
        }

        public function shouldReport(Throwable $e)
        {
            return true;
        }

        public function render($request, Throwable $e)
        {
            throw $e;
        }

        public function renderForConsole($output, Throwable $e)
        {
            throw $e;
        }
    });

    $owner = User::query()->firstOrFail();
    affiliateLifecycleProperty('lifecycle-half-null-property', 'Lifecycle Half Null Property');

    OwnerContext::withOwner(null, static fn (): TrackedProperty => affiliateLifecycleProperty('lifecycle-half-null-global-property', 'Lifecycle Half Null Global Property'));

    $affiliate = affiliateLifecycleAffiliate($owner, 'HALFNULL-ALI');
    $affiliate->forceFill(['owner_id' => null])->save();
    $affiliate = $affiliate->fresh() ?? $affiliate;

    expect(fn (): ?SignalEvent => app(CommerceSignalsRecorder::class)->recordAffiliateCreated($affiliate))
        ->toThrow(InvalidArgumentException::class, 'both be present');

    // Registered late so the model-created auto-dispatch above is not
    // observed; only the half-null manual dispatch below is recorded.
    Event::listen(AffiliateCreated::class, RecordCommerceSignal::class);

    Event::dispatch(new AffiliateCreated($affiliate));

    $reported = app(ExceptionHandler::class)->reported;

    expect($reported)->toHaveCount(1)
        ->and($reported[0])->toBeInstanceOf(CommerceSignalRecordingFailed::class)
        ->and(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0);
});

it('ignores created dispatches for missing affiliate rows', function (): void {
    Event::listen(AffiliateCreated::class, RecordCommerceSignal::class);

    $owner = User::query()->firstOrFail();
    affiliateLifecycleProperty('lifecycle-ghost-property', 'Lifecycle Ghost Property');

    $ghost = new Affiliate;
    $ghost->id = (string) Str::uuid();
    $ghost->exists = true;

    Event::dispatch(new AffiliateCreated($ghost));

    expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0)
        ->and(app(CommerceSignalsRecorder::class)->recordAffiliateCreated($ghost))->toBeNull();
});

it('records nothing for joins and fraud without a matching property', function (): void {
    Event::listen(AffiliateProgramJoined::class, RecordCommerceSignal::class);
    Event::listen(FraudSignalDetected::class, RecordCommerceSignal::class);

    $owner = User::query()->firstOrFail();
    $affiliate = affiliateLifecycleAffiliate($owner, 'NOPROP-JOIN-ALI');

    $program = AffiliateProgram::query()->create([
        'name' => 'No Property Program',
        'slug' => 'no-property-program',
    ]);

    $membership = affiliateLifecycleMembership($affiliate, $program);

    Event::dispatch(new AffiliateProgramJoined($affiliate, $program, $membership));

    $signal = AffiliateFraudSignal::query()->create([
        'affiliate_id' => $affiliate->getKey(),
        'rule_code' => 'velocity',
        'risk_points' => 10,
        'severity' => FraudSeverity::Low,
        'description' => 'No property probe',
        'detected_at' => now(),
    ]);

    expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0)
        ->and(app(CommerceSignalsRecorder::class)->recordAffiliateProgramJoined($affiliate, $program, $membership))->toBeNull()
        ->and(app(CommerceSignalsRecorder::class)->recordAffiliateFraudSignalDetected($signal))->toBeNull();
});

it('records nothing when the owner property is ambiguous', function (): void {
    Event::listen(AffiliateCreated::class, RecordCommerceSignal::class);

    $owner = User::query()->firstOrFail();
    affiliateLifecycleProperty('lifecycle-ambiguous-one-property', 'Lifecycle Ambiguous One Property');
    affiliateLifecycleProperty('lifecycle-ambiguous-two-property', 'Lifecycle Ambiguous Two Property');
    $affiliate = affiliateLifecycleAffiliate($owner, 'AMBIGUOUS-ALI');

    Event::dispatch(new AffiliateCreated($affiliate));

    expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0)
        ->and(app(CommerceSignalsRecorder::class)->recordAffiliateCreated($affiliate))->toBeNull();
});

it('uses the tracked property currency for lifecycle outputs', function (): void {
    Event::listen(AffiliateCreated::class, RecordCommerceSignal::class);
    Event::listen(AffiliateProgramJoined::class, RecordCommerceSignal::class);
    Event::listen(FraudSignalDetected::class, RecordCommerceSignal::class);

    $owner = User::query()->firstOrFail();

    TrackedProperty::query()->create([
        'name' => 'Lifecycle USD Property',
        'slug' => 'lifecycle-usd-property',
        'type' => 'website',
        'currency' => 'USD',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    $affiliate = affiliateLifecycleAffiliate($owner, 'USD-ALI');

    $program = AffiliateProgram::query()->create([
        'name' => 'USD Program',
        'slug' => 'usd-program',
    ]);

    $membership = affiliateLifecycleMembership($affiliate, $program);

    Event::dispatch(new AffiliateCreated($affiliate));
    Event::dispatch(new AffiliateProgramJoined($affiliate, $program, $membership));

    AffiliateFraudSignal::query()->create([
        'affiliate_id' => $affiliate->getKey(),
        'rule_code' => 'velocity',
        'risk_points' => 10,
        'severity' => FraudSeverity::Low,
        'description' => 'USD currency probe',
        'detected_at' => now(),
    ]);

    $currencies = SignalEvent::query()->withoutOwnerScope()->pluck('currency')->all();

    expect($currencies)->toHaveCount(3)
        ->and($currencies)->each->toBe('USD');
});

it('keeps distinct source identities distinct', function (): void {
    Event::listen(AffiliateCreated::class, RecordCommerceSignal::class);
    Event::listen(FraudSignalDetected::class, RecordCommerceSignal::class);

    $owner = User::query()->firstOrFail();
    affiliateLifecycleProperty('lifecycle-distinct-property', 'Lifecycle Distinct Property');
    $affiliateA = affiliateLifecycleAffiliate($owner, 'DISTINCT-A-ALI');
    $affiliateB = affiliateLifecycleAffiliate($owner, 'DISTINCT-B-ALI');

    Event::dispatch(new AffiliateCreated($affiliateA));
    Event::dispatch(new AffiliateCreated($affiliateB));

    AffiliateFraudSignal::query()->create([
        'affiliate_id' => $affiliateA->getKey(),
        'rule_code' => 'velocity',
        'risk_points' => 10,
        'severity' => FraudSeverity::Low,
        'description' => 'Distinct probe A',
        'detected_at' => now(),
    ]);
    AffiliateFraudSignal::query()->create([
        'affiliate_id' => $affiliateB->getKey(),
        'rule_code' => 'velocity',
        'risk_points' => 10,
        'severity' => FraudSeverity::Low,
        'description' => 'Distinct probe B',
        'detected_at' => now(),
    ]);

    $sourceIds = SignalEvent::query()->withoutOwnerScope()->pluck('source_event_id')->all();

    expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(4)
        ->and(array_unique($sourceIds))->toHaveCount(4);
});

it('rejects program and ownership mismatches on the strict recorder surface', function (): void {
    $owner = User::query()->firstOrFail();
    affiliateLifecycleProperty('lifecycle-strict-refs-property', 'Lifecycle Strict Refs Property');
    $affiliate = affiliateLifecycleAffiliate($owner, 'STRICTREF-ALI');
    $otherAffiliate = affiliateLifecycleAffiliate($owner, 'STRICTREF-OTHER-ALI');

    $program = AffiliateProgram::query()->create([
        'name' => 'Strict Refs Program',
        'slug' => 'strict-refs-program',
    ]);
    $otherProgram = AffiliateProgram::query()->create([
        'name' => 'Strict Refs Other Program',
        'slug' => 'strict-refs-other-program',
    ]);

    $membership = affiliateLifecycleMembership($affiliate, $program);
    $recorder = app(CommerceSignalsRecorder::class);

    expect(fn (): ?SignalEvent => $recorder->recordAffiliateProgramJoined($affiliate, $otherProgram, $membership))
        ->toThrow(InvalidArgumentException::class, 'membership program_id does not match');

    $staleMembership = clone $membership;
    $staleMembership->forceFill(['affiliate_id' => $otherAffiliate->getKey()]);

    expect(fn (): ?SignalEvent => $recorder->recordAffiliateProgramJoined($affiliate, $program, $staleMembership))
        ->toThrow(InvalidArgumentException::class, 'supplied membership affiliate_id does not match');

    $movedAffiliate = clone $affiliate;
    $movedAffiliate->forceFill([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $otherAffiliate->getKey(),
    ]);

    expect(fn (): ?SignalEvent => $recorder->recordAffiliateCreated($movedAffiliate))
        ->toThrow(InvalidArgumentException::class, 'affiliate owner does not match');

    $signal = AffiliateFraudSignal::query()->create([
        'affiliate_id' => $affiliate->getKey(),
        'rule_code' => 'velocity',
        'risk_points' => 10,
        'severity' => FraudSeverity::Low,
        'description' => 'Strict refs probe',
        'detected_at' => now(),
    ]);

    $movedSignal = clone $signal;
    $movedSignal->forceFill(['affiliate_id' => $otherAffiliate->getKey()]);

    expect(fn (): ?SignalEvent => $recorder->recordAffiliateFraudSignalDetected($movedSignal))
        ->toThrow(InvalidArgumentException::class, 'supplied fraud signal affiliate_id does not match');

    $retargetedSignal = clone $signal;
    $retargetedSignal->forceFill(['conversion_id' => (string) Str::uuid()]);

    expect(fn (): ?SignalEvent => $recorder->recordAffiliateFraudSignalDetected($retargetedSignal))
        ->toThrow(InvalidArgumentException::class, 'supplied fraud signal conversion_id does not match');

    $retouchedSignal = clone $signal;
    $retouchedSignal->forceFill(['touchpoint_id' => (string) Str::uuid()]);

    expect(fn (): ?SignalEvent => $recorder->recordAffiliateFraudSignalDetected($retouchedSignal))
        ->toThrow(InvalidArgumentException::class, 'supplied fraud signal touchpoint_id does not match');

    $retieredMembership = clone $membership;
    $retieredMembership->forceFill(['tier_id' => (string) Str::uuid()]);

    expect(fn (): ?SignalEvent => $recorder->recordAffiliateProgramJoined($affiliate, $program, $retieredMembership))
        ->toThrow(InvalidArgumentException::class, 'supplied membership tier_id does not match');

    $movedProgram = clone $program;
    $movedProgram->forceFill([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ]);

    expect(fn (): ?SignalEvent => $recorder->recordAffiliateProgramJoined($affiliate, $movedProgram, $membership))
        ->toThrow(InvalidArgumentException::class, 'program owner does not match');
});

it('rejects the other half-null owner orientation', function (): void {
    $owner = User::query()->firstOrFail();
    affiliateLifecycleProperty('lifecycle-half-null-b-property', 'Lifecycle Half Null B Property');

    $affiliate = affiliateLifecycleAffiliate($owner, 'HALFNULL-B-ALI');
    $affiliate->forceFill(['owner_type' => null])->save();
    $affiliate = $affiliate->fresh() ?? $affiliate;

    expect(fn (): ?SignalEvent => app(CommerceSignalsRecorder::class)->recordAffiliateCreated($affiliate))
        ->toThrow(InvalidArgumentException::class, 'both be present')
        ->and(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0);
});

it('rejects tuples whose owner row no longer exists', function (): void {
    app()->instance(ExceptionHandler::class, new class implements ExceptionHandler
    {
        /** @var list<Throwable> */
        public array $reported = [];

        public function report(Throwable $e)
        {
            $this->reported[] = $e;
        }

        public function shouldReport(Throwable $e)
        {
            return true;
        }

        public function render($request, Throwable $e)
        {
            throw $e;
        }

        public function renderForConsole($output, Throwable $e)
        {
            throw $e;
        }
    });

    Event::listen(AffiliateCreated::class, RecordCommerceSignal::class);

    $owner = User::query()->create([
        'name' => 'Doomed Owner',
        'email' => 'doomed-owner@example.com',
        'password' => 'secret',
    ]);

    affiliateLifecycleProperty('lifecycle-doomed-property', 'Lifecycle Doomed Property');

    $affiliate = affiliateLifecycleAffiliate($owner, 'DOOMED-ALI');

    // Query-builder delete: removes the row without firing model cascades.
    User::query()->whereKey($owner->getKey())->delete();
    $affiliate = $affiliate->fresh() ?? $affiliate;

    expect(fn (): ?SignalEvent => app(CommerceSignalsRecorder::class)->recordAffiliateCreated($affiliate))
        ->toThrow(ModelNotFoundException::class);

    Event::dispatch(new AffiliateCreated($affiliate));

    $reported = app(ExceptionHandler::class)->reported;

    expect($reported)->toHaveCount(1)
        ->and($reported[0])->toBeInstanceOf(CommerceSignalRecordingFailed::class)
        ->and(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0);
});

it('ignores joins for missing program rows', function (): void {
    Event::listen(AffiliateProgramJoined::class, RecordCommerceSignal::class);

    $owner = User::query()->firstOrFail();
    affiliateLifecycleProperty('lifecycle-ghost-program-property', 'Lifecycle Ghost Program Property');
    $affiliate = affiliateLifecycleAffiliate($owner, 'GHOST-PROGRAM-ALI');

    $program = AffiliateProgram::query()->create([
        'name' => 'Ghost Program',
        'slug' => 'ghost-program',
    ]);

    $membership = affiliateLifecycleMembership($affiliate, $program);

    // Query-builder delete: removes the row without firing model cascades.
    AffiliateProgram::query()->whereKey($program->getKey())->delete();

    Event::dispatch(new AffiliateProgramJoined($affiliate, $program, $membership));

    expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0)
        ->and(app(CommerceSignalsRecorder::class)->recordAffiliateProgramJoined($affiliate, $program, $membership))->toBeNull();
});

it('records nothing for joins and fraud under ambiguous properties', function (): void {
    Event::listen(AffiliateProgramJoined::class, RecordCommerceSignal::class);
    Event::listen(FraudSignalDetected::class, RecordCommerceSignal::class);

    $owner = User::query()->firstOrFail();
    affiliateLifecycleProperty('lifecycle-ambiguous-join-one-property', 'Lifecycle Ambiguous Join One Property');
    affiliateLifecycleProperty('lifecycle-ambiguous-join-two-property', 'Lifecycle Ambiguous Join Two Property');
    $affiliate = affiliateLifecycleAffiliate($owner, 'AMBIGUOUS-JOIN-ALI');

    $program = AffiliateProgram::query()->create([
        'name' => 'Ambiguous Program',
        'slug' => 'ambiguous-program',
    ]);

    $membership = affiliateLifecycleMembership($affiliate, $program);

    Event::dispatch(new AffiliateProgramJoined($affiliate, $program, $membership));

    $signal = AffiliateFraudSignal::query()->create([
        'affiliate_id' => $affiliate->getKey(),
        'rule_code' => 'velocity',
        'risk_points' => 10,
        'severity' => FraudSeverity::Low,
        'description' => 'Ambiguous probe',
        'detected_at' => now(),
    ]);

    expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0)
        ->and(app(CommerceSignalsRecorder::class)->recordAffiliateProgramJoined($affiliate, $program, $membership))->toBeNull()
        ->and(app(CommerceSignalsRecorder::class)->recordAffiliateFraudSignalDetected($signal))->toBeNull();
});

it('routes joins under affiliates enforcement', function (): void {
    config()->set('affiliates.owner.enabled', true);

    Event::listen(AffiliateProgramJoined::class, RecordCommerceSignal::class);

    $ownerB = User::query()->create([
        'name' => 'Join Enforcement Owner B',
        'email' => 'join-enforcement-owner-b@example.com',
        'password' => 'secret',
    ]);

    $propertyB = OwnerContext::withOwner($ownerB, static fn (): TrackedProperty => affiliateLifecycleProperty('lifecycle-join-enforcement-b-property', 'Lifecycle Join Enforcement B Property'));

    [$affiliate, $program, $membership] = OwnerContext::withOwner($ownerB, static function (): array {
        $affiliate = Affiliate::query()->create([
            'code' => 'JOIN-ENFORCEMENT-ALI',
            'name' => 'Join Enforcement Ali',
            'status' => Active::class,
            'commission_type' => CommissionType::Percentage->value,
            'commission_rate' => 1000,
            'currency' => 'MYR',
        ]);

        $program = AffiliateProgram::query()->create([
            'name' => 'Join Enforcement Program',
            'slug' => 'join-enforcement-program',
        ]);

        $membership = AffiliateProgramMembership::query()->create([
            'affiliate_id' => $affiliate->getKey(),
            'program_id' => $program->getKey(),
            'status' => MembershipStatus::Approved,
            'applied_at' => now()->subDay(),
            'approved_at' => now(),
        ]);

        return [$affiliate->fresh() ?? $affiliate, $program, $membership];
    });

    Event::dispatch(new AffiliateProgramJoined($affiliate, $program, $membership));

    expect(SignalEvent::query()->withoutOwnerScope()->sole()->tracked_property_id)->toBe($propertyB->id);
});

it('rejects half-null owners for joins and fraud', function (): void {
    $owner = User::query()->firstOrFail();
    affiliateLifecycleProperty('lifecycle-half-null-join-property', 'Lifecycle Half Null Join Property');

    $affiliate = affiliateLifecycleAffiliate($owner, 'HALFNULL-JOIN-ALI');
    $affiliate->forceFill(['owner_id' => null])->save();
    $affiliate = $affiliate->fresh() ?? $affiliate;

    $program = AffiliateProgram::query()->create([
        'name' => 'Half Null Join Program',
        'slug' => 'half-null-join-program',
    ]);

    $membership = affiliateLifecycleMembership($affiliate, $program);

    $signal = AffiliateFraudSignal::query()->create([
        'affiliate_id' => $affiliate->getKey(),
        'rule_code' => 'velocity',
        'risk_points' => 10,
        'severity' => FraudSeverity::Low,
        'description' => 'Half null join probe',
        'detected_at' => now(),
    ]);

    $recorder = app(CommerceSignalsRecorder::class);

    expect(fn (): ?SignalEvent => $recorder->recordAffiliateProgramJoined($affiliate, $program, $membership))
        ->toThrow(InvalidArgumentException::class, 'both be present')
        ->and(fn (): ?SignalEvent => $recorder->recordAffiliateFraudSignalDetected($signal))
        ->toThrow(InvalidArgumentException::class, 'both be present')
        ->and(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0);
});

it('rejects joins and fraud whose owner row no longer exists', function (): void {
    $owner = User::query()->create([
        'name' => 'Doomed Join Owner',
        'email' => 'doomed-join-owner@example.com',
        'password' => 'secret',
    ]);

    affiliateLifecycleProperty('lifecycle-doomed-join-property', 'Lifecycle Doomed Join Property');

    $affiliate = affiliateLifecycleAffiliate($owner, 'DOOMED-JOIN-ALI');

    $program = AffiliateProgram::query()->create([
        'name' => 'Doomed Join Program',
        'slug' => 'doomed-join-program',
    ]);

    $membership = affiliateLifecycleMembership($affiliate, $program);

    $signal = AffiliateFraudSignal::query()->create([
        'affiliate_id' => $affiliate->getKey(),
        'rule_code' => 'velocity',
        'risk_points' => 10,
        'severity' => FraudSeverity::Low,
        'description' => 'Doomed join probe',
        'detected_at' => now(),
    ]);

    // Query-builder delete: removes the row without firing model cascades.
    User::query()->whereKey($owner->getKey())->delete();
    $affiliate = $affiliate->fresh() ?? $affiliate;

    $recorder = app(CommerceSignalsRecorder::class);

    expect(fn (): ?SignalEvent => $recorder->recordAffiliateProgramJoined($affiliate, $program, $membership))
        ->toThrow(ModelNotFoundException::class)
        ->and(fn (): ?SignalEvent => $recorder->recordAffiliateFraudSignalDetected($signal))
        ->toThrow(ModelNotFoundException::class)
        ->and(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0);
});

it('ignores joins and fraud for missing affiliate rows', function (): void {
    $owner = User::query()->firstOrFail();
    affiliateLifecycleProperty('lifecycle-ghost-affiliate-property', 'Lifecycle Ghost Affiliate Property');
    $affiliate = affiliateLifecycleAffiliate($owner, 'GHOST-AFFILIATE-ALI');

    $program = AffiliateProgram::query()->create([
        'name' => 'Ghost Affiliate Program',
        'slug' => 'ghost-affiliate-program',
    ]);

    $membership = affiliateLifecycleMembership($affiliate, $program);

    $signal = AffiliateFraudSignal::query()->create([
        'affiliate_id' => $affiliate->getKey(),
        'rule_code' => 'velocity',
        'risk_points' => 10,
        'severity' => FraudSeverity::Low,
        'description' => 'Ghost affiliate probe',
        'detected_at' => now(),
    ]);

    // Query-builder delete: removes the row without firing model cascades.
    Affiliate::query()->whereKey($affiliate->getKey())->delete();

    // Registered late so the model-created auto-dispatch above is not
    // observed; only the ghost dispatches below are recorded.
    Event::listen(AffiliateProgramJoined::class, RecordCommerceSignal::class);
    Event::listen(FraudSignalDetected::class, RecordCommerceSignal::class);

    Event::dispatch(new AffiliateProgramJoined($affiliate, $program, $membership));
    Event::dispatch(new FraudSignalDetected($signal));

    expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0)
        ->and(app(CommerceSignalsRecorder::class)->recordAffiliateProgramJoined($affiliate, $program, $membership))->toBeNull()
        ->and(app(CommerceSignalsRecorder::class)->recordAffiliateFraudSignalDetected($signal))->toBeNull();
});

it('routes joins and fraud across owners and global scope under enforcement', function (): void {
    config()->set('affiliates.owner.enabled', true);

    Event::listen(AffiliateProgramJoined::class, RecordCommerceSignal::class);
    Event::listen(FraudSignalDetected::class, RecordCommerceSignal::class);

    $ownerA = User::query()->firstOrFail();
    $ownerB = User::query()->create([
        'name' => 'Matrix Owner B',
        'email' => 'matrix-owner-b@example.com',
        'password' => 'secret',
    ]);

    $propertyA = OwnerContext::withOwner($ownerA, static fn (): TrackedProperty => affiliateLifecycleProperty('lifecycle-matrix-a-property', 'Lifecycle Matrix A Property'));
    $propertyB = OwnerContext::withOwner($ownerB, static fn (): TrackedProperty => affiliateLifecycleProperty('lifecycle-matrix-b-property', 'Lifecycle Matrix B Property'));
    $propertyGlobal = OwnerContext::withOwner(null, static fn (): TrackedProperty => affiliateLifecycleProperty('lifecycle-matrix-global-property', 'Lifecycle Matrix Global Property'));

    $affiliateA = affiliateLifecycleAffiliate($ownerA, 'MATRIX-A-ALI');
    $affiliateB = affiliateLifecycleAffiliate($ownerB, 'MATRIX-B-ALI');

    $affiliateGlobal = OwnerContext::withOwner(null, static function (): Affiliate {
        return Affiliate::query()->create([
            'code' => 'MATRIX-GLOBAL-ALI',
            'name' => 'Matrix Global Ali',
            'status' => Active::class,
            'commission_type' => CommissionType::Percentage->value,
            'commission_rate' => 1000,
            'currency' => 'MYR',
        ]);
    });

    $programA = OwnerContext::withOwner($ownerA, static fn (): AffiliateProgram => AffiliateProgram::query()->create([
        'name' => 'Matrix A Program',
        'slug' => 'matrix-a-program',
    ]));
    $programB = OwnerContext::withOwner($ownerB, static fn (): AffiliateProgram => AffiliateProgram::query()->create([
        'name' => 'Matrix B Program',
        'slug' => 'matrix-b-program',
    ]));
    $programGlobal = OwnerContext::withOwner(null, static fn (): AffiliateProgram => AffiliateProgram::query()->create([
        'name' => 'Matrix Global Program',
        'slug' => 'matrix-global-program',
    ]));

    $membershipA = OwnerContext::withOwner($ownerA, static fn () => affiliateLifecycleMembership($affiliateA, $programA));
    $membershipB = OwnerContext::withOwner($ownerB, static fn () => affiliateLifecycleMembership($affiliateB, $programB));
    $membershipGlobal = OwnerContext::withOwner(null, static fn () => affiliateLifecycleMembership($affiliateGlobal, $programGlobal));

    Event::dispatch(new AffiliateProgramJoined($affiliateA, $programA, $membershipA));
    Event::dispatch(new AffiliateProgramJoined($affiliateB, $programB, $membershipB));
    Event::dispatch(new AffiliateProgramJoined($affiliateGlobal, $programGlobal, $membershipGlobal));

    OwnerContext::withOwner(null, static function () use ($affiliateGlobal): void {
        AffiliateFraudSignal::query()->create([
            'affiliate_id' => $affiliateGlobal->getKey(),
            'rule_code' => 'velocity',
            'risk_points' => 10,
            'severity' => FraudSeverity::Low,
            'description' => 'Matrix global fraud probe',
            'detected_at' => now(),
        ]);
    });

    $events = SignalEvent::query()->withoutOwnerScope()->get();

    expect($events)->toHaveCount(4);

    $bySource = $events->keyBy('source_event_id');

    expect($bySource[(string) $membershipA->getKey()]->tracked_property_id)->toBe($propertyA->id)
        ->and($bySource[(string) $membershipB->getKey()]->tracked_property_id)->toBe($propertyB->id)
        ->and($bySource[(string) $membershipGlobal->getKey()]->tracked_property_id)->toBe($propertyGlobal->id)
        ->and($events->where('event_name', 'affiliate.fraud.detected')->sole()->tracked_property_id)->toBe($propertyGlobal->id);
});

it('rejects reverse optional reference mismatches on the strict recorder surface', function (): void {
    $owner = User::query()->firstOrFail();
    affiliateLifecycleProperty('lifecycle-reverse-refs-property', 'Lifecycle Reverse Refs Property');
    $affiliate = affiliateLifecycleAffiliate($owner, 'REVERSE-ALI');

    $program = AffiliateProgram::query()->create([
        'name' => 'Reverse Refs Program',
        'slug' => 'reverse-refs-program',
    ]);

    $tier = AffiliateProgramTier::query()->create([
        'program_id' => $program->getKey(),
        'name' => 'Gold',
        'level' => 1,
        'commission_rate_basis_points' => 1500,
    ]);

    $membership = AffiliateProgramMembership::query()->create([
        'affiliate_id' => $affiliate->getKey(),
        'program_id' => $program->getKey(),
        'tier_id' => $tier->getKey(),
        'status' => MembershipStatus::Approved,
        'applied_at' => now()->subDay(),
        'approved_at' => now(),
    ]);

    $signal = AffiliateFraudSignal::query()->create([
        'affiliate_id' => $affiliate->getKey(),
        'conversion_id' => (string) Str::uuid(),
        'touchpoint_id' => (string) Str::uuid(),
        'rule_code' => 'velocity',
        'risk_points' => 10,
        'severity' => FraudSeverity::Low,
        'description' => 'Reverse refs probe',
        'detected_at' => now(),
    ]);

    $recorder = app(CommerceSignalsRecorder::class);

    // Canonical present, supplied null.
    $untieredMembership = clone $membership;
    $untieredMembership->forceFill(['tier_id' => null]);

    expect(fn (): ?SignalEvent => $recorder->recordAffiliateProgramJoined($affiliate, $program, $untieredMembership))
        ->toThrow(InvalidArgumentException::class, 'supplied membership tier_id does not match');

    $unlinkedSignal = clone $signal;
    $unlinkedSignal->forceFill(['conversion_id' => null, 'touchpoint_id' => null]);

    expect(fn (): ?SignalEvent => $recorder->recordAffiliateFraudSignalDetected($unlinkedSignal))
        ->toThrow(InvalidArgumentException::class, 'supplied fraud signal conversion_id does not match');

    $touchpointOnlySignal = clone $signal;
    $touchpointOnlySignal->forceFill(['touchpoint_id' => null]);

    expect(fn (): ?SignalEvent => $recorder->recordAffiliateFraudSignalDetected($touchpointOnlySignal))
        ->toThrow(InvalidArgumentException::class, 'supplied fraud signal touchpoint_id does not match');

    // Both present but different.
    $retargetedSignal = clone $signal;
    $retargetedSignal->forceFill(['conversion_id' => (string) Str::uuid()]);

    expect(fn (): ?SignalEvent => $recorder->recordAffiliateFraudSignalDetected($retargetedSignal))
        ->toThrow(InvalidArgumentException::class, 'supplied fraud signal conversion_id does not match')
        ->and(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0);
});
