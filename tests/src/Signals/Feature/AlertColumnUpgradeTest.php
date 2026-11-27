<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(SignalsTestCase::class);

function legacyAlertMigration(): object
{
    return require dirname(__DIR__, 4) . '/packages/signals/database/migrations/2001_01_01_000013_align_encrypted_alert_columns.php';
}

it('aborts when legacy plaintext alert destinations remain', function (): void {
    Schema::create('legacy_alert_rules', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->text('inline_destinations')->nullable();
    });

    config()->set('signals.database.tables.alert_rules', 'legacy_alert_rules');

    DB::table('legacy_alert_rules')->insert([
        'id' => (string) Str::uuid(),
        'inline_destinations' => '{"webhook":"https://example.test"}',
    ]);

    expect(fn () => legacyAlertMigration()->up())->toThrow(RuntimeException::class);

    DB::table('legacy_alert_rules')->delete();

    legacyAlertMigration()->up();

    expect(Schema::hasTable('legacy_alert_rules'))->toBeTrue();
});
