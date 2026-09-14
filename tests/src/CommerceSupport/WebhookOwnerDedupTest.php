<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProcessor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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

it('upgrades legacy webhook tables to the owner-scoped unique', function (): void {
    Schema::dropIfExists('webhook_calls');

    Schema::create('webhook_calls', function (Blueprint $table): void {
        $table->bigIncrements('id');
        $table->string('name');
        $table->string('url', 512);
        $table->json('headers')->nullable();
        $table->json('payload')->nullable();
        $table->text('exception')->nullable();
        $table->timestampTz('processed_at')->nullable();
        $table->string('status', 50)->default('pending');
        $table->timestampTz('failed_at')->nullable();
        $table->integer('retry_count')->default(0);
        $table->timestampTz('last_retry_at')->nullable();
        $table->string('event_id', 191)->nullable();
        $table->string('event_type', 191)->nullable();
        $table->timestampsTz();
        $table->unique(['name', 'event_id', 'event_type']);
    });

    ownerDedupMigration('1970_01_01_000006_add_owner_dedup_to_webhook_calls_table.php.stub')->up();

    expect(Schema::hasColumn('webhook_calls', 'owner_type'))->toBeTrue()
        ->and(Schema::hasColumn('webhook_calls', 'owner_id'))->toBeTrue()
        ->and(Schema::hasColumn('webhook_calls', 'owner_hash'))->toBeTrue()
        ->and(Schema::hasIndex('webhook_calls', 'webhook_calls_name_event_id_event_type_unique'))->toBeFalse()
        ->and(Schema::hasIndex('webhook_calls', 'webhook_calls_owner_dedup_unique'))->toBeTrue();

    $first = WebhookCall::query()->create([
        'name' => 'owner-dedup',
        'url' => 'https://example.test/webhooks/owner-dedup',
        'headers' => [],
        'payload' => [
            'event_type' => 'payment.completed',
            'id' => 'evt_legacy_upgrade',
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
            'id' => 'evt_legacy_upgrade',
            '__owner_type' => 'team',
            '__owner_id' => 'owner-two',
        ],
        'exception' => null,
    ]);

    (new OwnerDedupWebhookProcessor($first))->handle();
    (new OwnerDedupWebhookProcessor($second))->handle();

    expect(OwnerDedupWebhookProcessor::$processed)->toHaveCount(2);
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
