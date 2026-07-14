<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Affiliates\CreateTrackingLink;
use AIArmada\Affiliates\Actions\Conversions\RecordAffiliateOutcome;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;

beforeEach(function (): void {
    $this->affiliate = Affiliate::create([
        'code' => 'LINK-AFFILIATE',
        'name' => 'Link Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 0,
        'currency' => 'MYR',
    ]);
});

test('it preserves a caller supplied tracking url for precomputed link schemes', function (): void {
    $link = app(CreateTrackingLink::class)->handle(
        affiliate: $this->affiliate,
        destinationUrl: 'https://ilmu360.test/majlis/ramadan',
        attributes: [
            'tracking_url' => 'https://ilmu360.test/majlis/ramadan',
            'custom_slug' => 'ramadan-share-token',
            'subject_type' => 'event',
            'subject_identifier' => 'event:ramadan',
        ],
    );

    expect($link->tracking_url)->toBe('https://ilmu360.test/majlis/ramadan')
        ->and($link->custom_slug)->toBe('ramadan-share-token')
        ->and($link->subject_type)->toBe('event');
});

test('it records an attributed outcome without requiring a cart', function (): void {
    $attribution = AffiliateAttribution::create([
        'affiliate_id' => $this->affiliate->getKey(),
        'affiliate_code' => $this->affiliate->code,
        'subject_type' => 'event',
        'subject_identifier' => 'event:ramadan',
        'subject_instance' => 'web',
        'metadata' => ['subject_id' => 'event-id'],
    ]);

    $conversion = app(RecordAffiliateOutcome::class)->handle(
        attribution: $attribution,
        conversionType: 'event_checkin',
        externalReference: 'event-checkin:1',
        payload: [
            'status' => ApprovedConversion::class,
            'metadata' => ['subject_id' => 'event-id'],
            'dispatch_event' => false,
        ],
    );

    expect($conversion)
        ->not()->toBeNull()
        ->and($conversion?->affiliateCode)->toBe($this->affiliate->code)
        ->and($conversion?->conversionType)->toBe('event_checkin');
});
