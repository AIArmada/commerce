<?php

declare(strict_types=1);

use AIArmada\Communications\Enums\SuppressionReason;
use AIArmada\Communications\Models\CommunicationPreference;
use AIArmada\Communications\Models\CommunicationSuppression;
use Carbon\CarbonImmutable;

test('creates suppression record', function (): void {
    $suppression = CommunicationSuppression::create([
        'destination_hash' => hash('sha256', 'bounced@example.com'),
        'channel' => 'mail',
        'reason' => SuppressionReason::Bounced,
        'starts_at' => CarbonImmutable::now(),
    ]);

    expect($suppression->id)->toBeUuid();
    expect($suppression->channel)->toBe('mail');
    expect($suppression->reason)->toBeInstanceOf(SuppressionReason::class);
    expect($suppression->reason->value)->toBe('bounced');
    expect($suppression->starts_at)->not->toBeNull();
});

test('suppression can be lifted', function (): void {
    $suppression = CommunicationSuppression::create([
        'destination_hash' => hash('sha256', 'bounced@example.com'),
        'channel' => 'mail',
        'reason' => SuppressionReason::Bounced,
        'starts_at' => CarbonImmutable::now(),
    ]);

    $suppression->update([
        'lifted_at' => CarbonImmutable::now(),
        'source' => 'manual',
    ]);

    $fresh = CommunicationSuppression::find($suppression->id);
    expect($fresh->lifted_at)->not->toBeNull();
    expect($fresh->source)->toBe('manual');
});

test('stores suppression metadata', function (): void {
    $suppression = CommunicationSuppression::create([
        'destination_hash' => hash('sha256', 'test@example.com'),
        'channel' => 'mail',
        'reason' => SuppressionReason::Bounced,
        'starts_at' => CarbonImmutable::now(),
        'metadata' => ['bounce_code' => 550, 'diagnostic' => 'user unknown'],
    ]);

    expect($suppression->metadata['bounce_code'])->toBe(550);
});

test('creates communication preference', function (): void {
    $preference = CommunicationPreference::create([
        'recipient_type' => 'user',
        'recipient_id' => 'usr-123',
        'channel' => 'mail',
        'category' => 'notification',
    ]);

    expect($preference->id)->toBeUuid();
    expect($preference->recipient_type)->toBe('user');
    expect($preference->channel)->toBe('mail');
    expect($preference->category)->toBe('notification');
});

test('preference stores metadata', function (): void {
    $preference = CommunicationPreference::create([
        'recipient_type' => 'user',
        'recipient_id' => 'usr-456',
        'channel' => 'mail',
        'category' => 'marketing',
        'metadata' => ['source' => 'registration', 'version' => 2],
    ]);

    expect($preference->metadata['source'])->toBe('registration');
});
