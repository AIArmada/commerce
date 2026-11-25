<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Features\Webhooks\Actions\ProcessWebhookCallAction;
use AIArmada\CommerceSupport\Features\Webhooks\EntryPoints\CommerceWebhookProcessor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\WebhookClient\Models\WebhookCall;

beforeEach(function (): void {
    OwnerDedupWebhookProcessor::reset();

    Schema::dropIfExists('webhook_calls');

    ownerDedupMigration('1970_01_01_000003_create_webhook_calls_table.php.stub')->up();
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

it('yields distinct payload hashes for distinct invalid utf-8 payloads', function (): void {
    $method = new ReflectionMethod(ProcessWebhookCallAction::class, 'payloadHash');
    $method->setAccessible(true);

    $first = $method->invoke(null, ['event_type' => 'payment.completed', 'raw' => "\xB1\x31"]);
    $second = $method->invoke(null, ['event_type' => 'payment.completed', 'raw' => "\xB1\x32"]);

    expect($first)->toBeString()
        ->and($second)->toBeString()
        ->and($first)->not->toBe($second);
});

it('clears dedup stamps on the unique-violation loser', function (): void {
    $sharedEventId = 'evt_collision';

    $winner = WebhookCall::query()->create([
        'name' => 'owner-dedup',
        'url' => 'https://example.test/webhooks/owner-dedup',
        'headers' => [],
        'payload' => ['event_type' => 'payment.completed', 'id' => $sharedEventId],
        'exception' => null,
    ]);

    $loser = WebhookCall::query()->create([
        'name' => 'owner-dedup',
        'url' => 'https://example.test/webhooks/owner-dedup',
        'headers' => [],
        'payload' => ['event_type' => 'payment.completed', 'id' => 'evt_original'],
        'exception' => null,
    ]);

    (new OwnerDedupWebhookProcessor($loser))->handle();

    DB::table((new WebhookCall)->getTable())
        ->where('id', $loser->getKey())
        ->update([
            'processed_at' => null,
            'status' => 'pending',
            'payload' => json_encode(['event_type' => 'payment.completed', 'id' => $sharedEventId]),
        ]);

    (new OwnerDedupWebhookProcessor($winner))->handle();

    $hydratedLoser = WebhookCall::query()->findOrFail($loser->getKey());

    expect($hydratedLoser->getAttribute('event_id'))->toBe('evt_original');

    (new OwnerDedupWebhookProcessor($hydratedLoser))->handle();

    $settled = WebhookCall::query()->findOrFail($loser->getKey());

    expect($settled->getAttribute('processed_at'))->not->toBeNull()
        ->and($settled->getAttribute('status'))->toBe('processed')
        ->and($settled->getAttribute('event_id'))->toBeNull()
        ->and($settled->getAttribute('event_type'))->toBeNull()
        ->and($settled->getAttribute('owner_type'))->toBeNull()
        ->and($settled->getAttribute('owner_id'))->toBeNull()
        ->and($settled->getAttribute('owner_hash'))->toBeNull();
});

it('avoids schema probes and redundant by-key selects while handling a webhook', function (): void {
    $call = WebhookCall::query()->create([
        'name' => 'owner-dedup',
        'url' => 'https://example.test/webhooks/owner-dedup',
        'headers' => [],
        'payload' => ['event_type' => 'payment.completed', 'id' => 'evt_no_probe'],
        'exception' => null,
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    (new OwnerDedupWebhookProcessor($call))->handle();

    $log = DB::getQueryLog();
    DB::disableQueryLog();

    $schemaProbes = array_filter($log, static fn (array $entry): bool => str_contains((string) $entry['query'], 'information_schema')
        || str_contains((string) $entry['query'], 'pragma_table_info'));

    $byKeySelects = array_filter($log, static function (array $entry): bool {
        $sql = mb_strtolower(mb_ltrim((string) $entry['query']));

        return str_starts_with($sql, 'select')
            && str_contains($sql, 'webhook_calls')
            && str_contains($sql, '"id" = ');
    });

    expect($schemaProbes)->toBe([])
        ->and($byKeySelects)->toHaveCount(1)
        ->and(OwnerDedupWebhookProcessor::$processed)->toHaveCount(1);
});
