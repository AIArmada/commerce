<?php

declare(strict_types=1);

use AIArmada\Affiliates\Contracts\AffiliateWebhookTransport;
use AIArmada\Affiliates\Drivers\Webhooks\PinnedAffiliateWebhookTransport;
use AIArmada\Affiliates\Features\Webhooks\Actions\DeliverAffiliateWebhook;
use AIArmada\Affiliates\Features\Webhooks\EntryPoints\Jobs\DispatchAffiliateWebhook;
use AIArmada\Affiliates\Features\Webhooks\Models\AffiliateWebhookDelivery;
use AIArmada\CommerceSupport\Drivers\Http\PinnedHttpClient;
use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

it('rejects mixed public and private DNS answers before affiliate webhook transport', function (): void {
    Http::fake();
    config(['affiliates.events.dispatch_webhooks' => true]);
    $delivery = AffiliateWebhookDelivery::query()->create([
        'event_id' => (string) Str::uuid(),
        'event_type' => 'conversion',
        'destination_key' => hash('sha256', 'https://mixed.example/webhook'),
        'endpoint' => 'https://mixed.example/webhook',
        'headers' => [],
        'body_json' => '{"type":"conversion","data":{}}',
        'status' => 'pending',
        'max_attempts' => 2,
        'available_at' => now(),
    ]);
    $job = new DispatchAffiliateWebhook($delivery->id);

    app()->instance(AffiliateWebhookTransport::class, new PinnedAffiliateWebhookTransport(
        new PublicHttpUrlGuard(static fn (string $host): array => ['93.184.216.34', '10.0.0.7']),
        new PinnedHttpClient,
    ));

    expect(fn () => $job->handle(app(DeliverAffiliateWebhook::class)))->toThrow(RuntimeException::class, 'Affiliate webhook delivery failed.');

    expect($delivery->fresh()->status)->toBe('failed')
        ->and($delivery->fresh()->last_error_code)->toBe('unsafe_destination')
        ->and($delivery->fresh()->attempt_count)->toBe(1);
    Http::assertNothingSent();

    $job->failed(new RuntimeException('queue wrapper'));

    expect($delivery->fresh()->status)->toBe('dead')
        ->and($delivery->fresh()->last_error_code)->toBe('unsafe_destination');
});

it('produces a concrete curl pin from the exact validated DNS answer', function (): void {
    $target = (new PublicHttpUrlGuard(static fn (string $host): array => ['93.184.216.34']))
        ->validate('https://hooks.example/path?event=1');

    expect($target->url)->toBe('https://hooks.example/path?event=1')
        ->and($target->selectedIp)->toBe('93.184.216.34')
        ->and($target->curlResolveEntry())->toBe('hooks.example:443:93.184.216.34');
});
