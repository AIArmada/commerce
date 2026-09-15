<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Actions\ProcessWebhookCallAction;
use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProcessor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\WebhookClient\Models\WebhookCall;

beforeEach(function (): void {
    OwnerDedupWebhookProcessor::reset();

    Schema::dropIfExists('webhook_calls');

    ownerDedupMigration('1970_01_01_000004_create_webhook_calls_table.php.stub')->up();
});

it('creates webhook calls with owner dedup columns and an owner-scoped unique', function (): void {
    expect(Schema::hasColumn('webhook_calls', 'owner_type'))->toBeTrue()
        ->and(Schema::hasColumn('webhook_calls', 'owner_id'))->toBeTrue()
        ->and(Schema::hasColumn('webhook_calls', 'owner_hash'))->toBeTrue()
        ->and(Schema::hasIndex('webhook_calls', 'webhook_calls_owner_dedup_unique'))->toBeTrue();
});

it('processes identical provider events for two different owners', function (): void {
    $first = WebhookCall::query()->create([
        'name' => 'owner-dedup',
        'url' => 'https://example.test/webhooks/owner-dedup',
        'headers' => [],
        'payload' => [
            'event_type' => 'payment.completed',
            'id' => 'evt_shared_across_owners',
            '__owner_type' => 'team',
            '__owner_id' => 'owner-one',
        ],
        'exception' => null,
    ]);

    $second = WebhookCall::query()->create([
        'name' => 'owner-dedup',
        'url' => 'https://example.test/webhooks/owner-dedup',
        'headers' => [],
        'payload' => [
            'event_type' => 'payment.completed',
            'id' => 'evt_shared_across_owners',
            '__owner_type' => 'team',
            '__owner_id' => 'owner-two',
        ],
        'exception' => null,
    ]);

    (new OwnerDedupWebhookProcessor($first))->handle();
    (new OwnerDedupWebhookProcessor($second))->handle();

    expect(OwnerDedupWebhookProcessor::$processed)->toHaveCount(2)
        ->and($first->fresh()?->processed_at)->not->toBeNull()
        ->and($second->fresh()?->processed_at)->not->toBeNull()
        ->and($first->fresh()?->getAttribute('owner_hash'))->not->toBeNull()
        ->and($first->fresh()?->getAttribute('owner_hash'))
        ->not->toBe($second->fresh()?->getAttribute('owner_hash'));
});

it('still deduplicates repeated deliveries for the same owner', function (): void {
    $payload = [
        'event_type' => 'payment.completed',
        'id' => 'evt_same_owner_repeat',
        '__owner_type' => 'team',
        '__owner_id' => 'owner-one',
    ];

    $first = WebhookCall::query()->create([
        'name' => 'owner-dedup',
        'url' => 'https://example.test/webhooks/owner-dedup',
        'headers' => [],
        'payload' => $payload,
        'exception' => null,
    ]);

    $second = WebhookCall::query()->create([
        'name' => 'owner-dedup',
        'url' => 'https://example.test/webhooks/owner-dedup',
        'headers' => [],
        'payload' => $payload,
        'exception' => null,
    ]);

    (new OwnerDedupWebhookProcessor($first))->handle();
    (new OwnerDedupWebhookProcessor($second))->handle();

    expect(OwnerDedupWebhookProcessor::$processed)->toHaveCount(1)
        ->and($first->fresh()?->processed_at)->not->toBeNull()
        ->and($second->fresh()?->processed_at)->not->toBeNull();
});

it('stamps ownerless deliveries with the ownerless sentinel so they collide', function (): void {
    $payload = [
        'event_type' => 'payment.completed',
        'id' => 'evt_ownerless_repeat',
    ];

    $first = WebhookCall::query()->create([
        'name' => 'owner-dedup',
        'url' => 'https://example.test/webhooks/owner-dedup',
        'headers' => [],
        'payload' => $payload,
        'exception' => null,
    ]);

    $second = WebhookCall::query()->create([
        'name' => 'owner-dedup',
        'url' => 'https://example.test/webhooks/owner-dedup',
        'headers' => [],
        'payload' => $payload,
        'exception' => null,
    ]);

    (new OwnerDedupWebhookProcessor($first))->handle();
    (new OwnerDedupWebhookProcessor($second))->handle();

    expect(OwnerDedupWebhookProcessor::$processed)->toHaveCount(1)
        ->and($first->fresh()?->processed_at)->not->toBeNull()
        ->and($second->fresh()?->processed_at)->not->toBeNull()
        ->and($first->fresh()?->getAttribute('owner_hash'))->not->toBeNull()
        ->and($first->fresh()?->getAttribute('owner_hash'))
        ->toBe(ProcessWebhookCallAction::ownerHashFor([null, null]));
});

it('deduplicates deliveries without a provider event id by payload hash', function (): void {
    $payload = [
        'event_type' => 'payment.completed',
        'amount_minor' => 1000,
    ];

    $first = WebhookCall::query()->create([
        'name' => 'owner-dedup',
        'url' => 'https://example.test/webhooks/owner-dedup',
        'headers' => [],
        'payload' => $payload,
        'exception' => null,
    ]);

    $second = WebhookCall::query()->create([
        'name' => 'owner-dedup',
        'url' => 'https://example.test/webhooks/owner-dedup',
        'headers' => [],
        'payload' => $payload,
        'exception' => null,
    ]);

    $distinct = WebhookCall::query()->create([
        'name' => 'owner-dedup',
        'url' => 'https://example.test/webhooks/owner-dedup',
        'headers' => [],
        'payload' => [
            'event_type' => 'payment.completed',
            'amount_minor' => 2000,
        ],
        'exception' => null,
    ]);

    (new OwnerDedupWebhookProcessor($first))->handle();
    (new OwnerDedupWebhookProcessor($second))->handle();
    (new OwnerDedupWebhookProcessor($distinct))->handle();

    expect(OwnerDedupWebhookProcessor::$processed)->toHaveCount(2)
        ->and($first->fresh()?->getAttribute('event_id'))->not->toBeNull()
        ->and($second->fresh()?->processed_at)->not->toBeNull()
        ->and($second->fresh()?->getAttribute('event_id'))->toBeNull()
        ->and($distinct->fresh()?->getAttribute('event_id'))->not->toBeNull()
        ->and($distinct->fresh()?->getAttribute('event_id'))->not->toBe($first->fresh()?->getAttribute('event_id'));
});

function ownerDedupMigration(string $migrationFile): Migration
{
    /** @var Migration $migration */
    $migration = require dirname(__DIR__, 3) . '/packages/commerce-support/database/migrations/' . $migrationFile;

    return $migration;
}

final class OwnerDedupWebhookProcessor extends CommerceWebhookProcessor
{
    /**
     * @var array<int, array{0: string, 1: array<string, mixed>}>
     */
    public static array $processed = [];

    public static function reset(): void
    {
        self::$processed = [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function processEvent(string $eventType, array $payload): void
    {
        self::$processed[] = [$eventType, $payload];
    }
}
