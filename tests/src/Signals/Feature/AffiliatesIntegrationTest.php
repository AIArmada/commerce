<?php

declare(strict_types=1);

use AIArmada\Affiliates\Data\AffiliateAttributionData;
use AIArmada\Affiliates\Data\AffiliateConversionData;
use AIArmada\Affiliates\Data\AffiliateData;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Events\AffiliateAttributed;
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

uses(SignalsTestCase::class);

beforeEach(function (): void {
    Schema::dropIfExists('affiliate_conversions');
    Schema::dropIfExists('affiliate_attributions');
    Schema::dropIfExists('affiliates');

    Schema::create('affiliates', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('code')->unique();
        $table->string('name');
        $table->string('status')->default(Active::class);
        $table->string('commission_type')->default(CommissionType::Percentage->value);
        $table->unsignedInteger('commission_rate')->default(1000);
        $table->string('currency', 3)->default('MYR');
        $table->string('default_voucher_code')->nullable();
        $table->json('metadata')->nullable();
        $table->nullableUuidMorphs('owner');
        $table->timestamps();
    });

    Schema::create('affiliate_attributions', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->foreignUuid('affiliate_id');
        $table->string('affiliate_code');
        $table->string('subject_identifier')->nullable();
        $table->string('subject_instance')->nullable();
        $table->string('cart_identifier')->nullable()->index();
        $table->string('cart_instance')->default('default');
        $table->string('cookie_value')->nullable()->index();
        $table->string('voucher_code')->nullable();
        $table->string('source')->nullable();
        $table->string('medium')->nullable();
        $table->string('campaign')->nullable();
        $table->string('term')->nullable();
        $table->string('content')->nullable();
        $table->text('landing_url')->nullable();
        $table->text('referrer_url')->nullable();
        $table->foreignUuid('user_id')->nullable();
        $table->nullableUuidMorphs('owner');
        $table->json('metadata')->nullable();
        $table->timestamp('last_seen_at')->nullable();
        $table->timestamp('expires_at')->nullable();
        $table->timestamps();
    });

    Schema::create('affiliate_conversions', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->foreignUuid('affiliate_id');
        $table->foreignUuid('affiliate_attribution_id')->nullable();
        $table->string('affiliate_code');
        $table->string('subject_identifier')->nullable();
        $table->string('subject_instance')->nullable();
        $table->string('cart_identifier')->nullable()->index();
        $table->string('cart_instance')->nullable();
        $table->string('voucher_code')->nullable();
        $table->string('external_reference')->nullable();
        $table->string('order_reference')->nullable();
        $table->string('conversion_type')->nullable();
        $table->unsignedBigInteger('subtotal_minor')->default(0);
        $table->unsignedBigInteger('value_minor')->default(0);
        $table->unsignedBigInteger('total_minor')->default(0);
        $table->unsignedBigInteger('commission_minor')->default(0);
        $table->string('commission_currency', 3)->default('MYR');
        $table->string('status')->default(ApprovedConversion::class);
        $table->string('channel')->nullable();
        $table->nullableUuidMorphs('owner');
        $table->json('metadata')->nullable();
        $table->timestamp('occurred_at')->nullable();
        $table->timestamps();
    });
});

it('records an affiliate attributed signal for the matching owner property', function (): void {
    $owner = User::query()->firstOrFail();
    $otherOwner = User::query()->create([
        'name' => 'Affiliate Other Owner',
        'email' => 'affiliate-other-owner@example.com',
        'password' => 'secret',
    ]);

    $property = TrackedProperty::query()->create([
        'name' => 'Owner A Affiliate Property',
        'slug' => 'owner-a-affiliate-property',
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    OwnerContext::withOwner($otherOwner, static function (): void {
        TrackedProperty::query()->create([
            'name' => 'Owner B Affiliate Property',
            'slug' => 'owner-b-affiliate-property',
            'type' => 'website',
            'currency' => 'MYR',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);
    });

    $affiliate = Affiliate::query()->create([
        'code' => 'USTAZ-ALI',
        'name' => 'Ustaz Ali',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage->value,
        'commission_rate' => 1000,
        'currency' => 'MYR',
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ]);

    $attribution = AffiliateAttribution::query()->create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'subject_identifier' => 'event:ramadan-series',
        'subject_instance' => 'share-link',
        'cart_identifier' => 'share-visit-001',
        'cart_instance' => 'share',
        'cookie_value' => 'cookie-share-001',
        'voucher_code' => 'DAKWAH10',
        'source' => 'whatsapp',
        'medium' => 'share',
        'campaign' => 'ramadan-series',
        'term' => 'halaqah',
        'content' => 'poster',
        'landing_url' => '/events/ramadan-series',
        'referrer_url' => 'https://wa.me/community',
        'user_id' => $owner->getKey(),
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
        'last_seen_at' => now(),
    ]);

    Event::dispatch(new AffiliateAttributed(
        AffiliateData::fromModel($affiliate),
        AffiliateAttributionData::fromModel($attribution),
    ));

    $event = SignalEvent::query()->withoutOwnerScope()->sole();

    expect($event->tracked_property_id)->toBe($property->id)
        ->and($event->event_name)->toBe('affiliate.attributed')
        ->and($event->event_category)->toBe('acquisition')
        ->and($event->signal_session_id)->not->toBeNull()
        ->and($event->signal_identity_id)->not->toBeNull()
        ->and($event->source)->toBe('whatsapp')
        ->and($event->medium)->toBe('share')
        ->and($event->campaign)->toBe('ramadan-series')
        ->and($event->path)->toBe('/events/ramadan-series')
        ->and($event->referrer)->toBe('https://wa.me/community')
        ->and($event->properties)->toMatchArray([
            'attribution_id' => $attribution->id,
            'affiliate_id' => $affiliate->id,
            'affiliate_code' => 'USTAZ-ALI',
            'subject_identifier' => 'event:ramadan-series',
            'subject_instance' => 'share-link',
            'cart_identifier' => 'share-visit-001',
            'cart_instance' => 'share',
            'cookie_value' => 'cookie-share-001',
            'voucher_code' => 'DAKWAH10',
        ]);
});

it('records an affiliate conversion signal for the matching owner property', function (): void {
    $owner = User::query()->firstOrFail();
    $otherOwner = User::query()->create([
        'name' => 'Affiliate Conversion Other Owner',
        'email' => 'affiliate-conversion-other-owner@example.com',
        'password' => 'secret',
    ]);

    $property = TrackedProperty::query()->create([
        'name' => 'Owner A Affiliate Conversion Property',
        'slug' => 'owner-a-affiliate-conversion-property',
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    OwnerContext::withOwner($otherOwner, static function (): void {
        TrackedProperty::query()->create([
            'name' => 'Owner B Affiliate Conversion Property',
            'slug' => 'owner-b-affiliate-conversion-property',
            'type' => 'website',
            'currency' => 'MYR',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);
    });

    $affiliate = Affiliate::query()->create([
        'code' => 'USTAZ-ZAYN',
        'name' => 'Ustaz Zayn',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage->value,
        'commission_rate' => 800,
        'currency' => 'MYR',
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ]);

    $attribution = AffiliateAttribution::query()->create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'subject_identifier' => 'event:weekly-tafsir',
        'subject_instance' => 'share-link',
        'cart_identifier' => 'share-conversion-001',
        'cart_instance' => 'share',
        'source' => 'telegram',
        'medium' => 'share',
        'campaign' => 'weekly-tafsir',
        'term' => 'series',
        'content' => 'caption',
        'landing_url' => '/events/weekly-tafsir',
        'referrer_url' => 'https://t.me/majlis',
        'user_id' => $owner->getKey(),
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
        'last_seen_at' => now(),
    ]);

    $conversion = AffiliateConversion::query()->create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_attribution_id' => $attribution->getKey(),
        'affiliate_code' => $affiliate->code,
        'subject_identifier' => 'event:weekly-tafsir',
        'subject_instance' => 'share-link',
        'cart_identifier' => 'share-conversion-001',
        'cart_instance' => 'share',
        'voucher_code' => 'TAFSIR15',
        'external_reference' => 'REG-SHARE-1001',
        'order_reference' => 'ORD-SHARE-1001',
        'conversion_type' => 'registration',
        'subtotal_minor' => 32000,
        'value_minor' => 28000,
        'total_minor' => 35000,
        'commission_minor' => 2800,
        'commission_currency' => 'MYR',
        'status' => ApprovedConversion::class,
        'channel' => 'share',
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
        'occurred_at' => now(),
    ]);

    Event::dispatch(new AffiliateConversionRecorded(
        AffiliateConversionData::fromModel($conversion),
    ));

    $event = SignalEvent::query()->withoutOwnerScope()->sole();

    expect($event->tracked_property_id)->toBe($property->id)
        ->and($event->event_name)->toBe('affiliate.conversion.recorded')
        ->and($event->event_category)->toBe('conversion')
        ->and($event->signal_session_id)->not->toBeNull()
        ->and($event->signal_identity_id)->not->toBeNull()
        ->and($event->revenue_minor)->toBe(28000)
        ->and($event->currency)->toBe('MYR')
        ->and($event->source)->toBe('telegram')
        ->and($event->medium)->toBe('share')
        ->and($event->campaign)->toBe('weekly-tafsir')
        ->and($event->properties)->toMatchArray([
            'conversion_id' => $conversion->id,
            'affiliate_id' => $affiliate->id,
            'affiliate_code' => 'USTAZ-ZAYN',
            'attribution_id' => $attribution->id,
            'subject_identifier' => 'event:weekly-tafsir',
            'subject_instance' => 'share-link',
            'cart_identifier' => 'share-conversion-001',
            'cart_instance' => 'share',
            'voucher_code' => 'TAFSIR15',
            'external_reference' => 'REG-SHARE-1001',
            'order_reference' => 'ORD-SHARE-1001',
            'conversion_type' => 'registration',
            'subtotal_minor' => 32000,
            'value_minor' => 28000,
            'total_minor' => 35000,
            'commission_minor' => 2800,
            'status' => 'approved',
            'channel' => 'share',
        ]);
});

it('ignores forged affiliate events that target another owner model id', function (): void {
    $ownerA = User::query()->firstOrFail();
    $ownerB = User::query()->create([
        'name' => 'Affiliate Forged Other Owner',
        'email' => 'affiliate-forged-owner@example.com',
        'password' => 'secret',
    ]);

    TrackedProperty::query()->create([
        'name' => 'Owner A Property',
        'slug' => 'owner-a-property',
        'type' => 'website',
        'currency' => 'MYR',
        'timezone' => 'UTC',
        'is_active' => true,
    ]);

    OwnerContext::withOwner($ownerB, static function (): void {
        TrackedProperty::query()->create([
            'name' => 'Owner B Property',
            'slug' => 'owner-b-property',
            'type' => 'website',
            'currency' => 'MYR',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);
    });

    $affiliateA = Affiliate::query()->create([
        'code' => 'OWNER-A-AFF',
        'name' => 'Owner A Affiliate',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage->value,
        'commission_rate' => 1000,
        'currency' => 'MYR',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    $affiliateB = Affiliate::query()->create([
        'code' => 'OWNER-B-AFF',
        'name' => 'Owner B Affiliate',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage->value,
        'commission_rate' => 1000,
        'currency' => 'MYR',
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    $targetAttribution = AffiliateAttribution::query()->create([
        'affiliate_id' => $affiliateA->getKey(),
        'affiliate_code' => $affiliateA->code,
        'subject_identifier' => 'event:owner-a',
        'subject_instance' => 'share',
        'cart_identifier' => 'share-a',
        'cart_instance' => 'share',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    Event::dispatch(new AffiliateAttributed(
        AffiliateData::fromModel($affiliateB),
        new AffiliateAttributionData(
            id: $targetAttribution->id,
            affiliateId: $affiliateB->id,
            affiliateCode: $affiliateB->code,
            subjectIdentifier: 'event:owner-b',
            subjectInstance: 'share',
            cartIdentifier: 'share-b',
            cartInstance: 'share',
            ownerType: $ownerB->getMorphClass(),
            ownerId: (string) $ownerB->getKey(),
        ),
    ));

    Event::dispatch(new AffiliateConversionRecorded(
        new AffiliateConversionData(
            id: AffiliateConversion::query()->create([
                'affiliate_id' => $affiliateA->getKey(),
                'affiliate_code' => $affiliateA->code,
                'affiliate_attribution_id' => $targetAttribution->getKey(),
                'subject_identifier' => 'event:owner-a',
                'subject_instance' => 'share',
                'owner_type' => $ownerA->getMorphClass(),
                'owner_id' => $ownerA->getKey(),
                'commission_currency' => 'MYR',
            ])->id,
            affiliateId: $affiliateB->id,
            affiliateCode: $affiliateB->code,
            subjectIdentifier: 'event:owner-b',
            subjectInstance: 'share',
            ownerType: $ownerB->getMorphClass(),
            ownerId: (string) $ownerB->getKey(),
        ),
    ));

    expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0);
});
