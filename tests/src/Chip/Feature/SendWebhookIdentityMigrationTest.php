<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('normalizes legacy integer Send webhook identities', function (): void {
    $table = 'send_webhooks';

    Schema::create($table, function (Blueprint $blueprint): void {
        $blueprint->integer('id')->primary();
        $blueprint->string('name');
    });

    DB::table($table)->insert([
        ['id' => 41, 'name' => 'Legacy webhook'],
    ]);

    config()->set('chip.database.table_prefix', '');
    $migration = require dirname(__DIR__, 4) . '/packages/chip/database/migrations/2026_07_11_000002_normalize_chip_send_webhook_identity.php';
    $migration->up();

    $record = DB::table($table)->first();

    expect($record->provider_webhook_id)->toBe(41)
        ->and($record->id)->toBeString()
        ->and(Schema::hasIndex($table, 'send_webhooks_provider_webhook_id_unique'))->toBeTrue();

    Schema::dropIfExists($table);
});
