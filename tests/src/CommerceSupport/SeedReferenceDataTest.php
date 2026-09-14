<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Actions\SeedCurrenciesAction;
use AIArmada\CommerceSupport\Actions\SeedLanguagesAction;
use AIArmada\CommerceSupport\Actions\SeedTimezonesAction;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('currencies');
    Schema::dropIfExists('languages');
    Schema::dropIfExists('timezones');

    Schema::create('languages', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('code', 10)->unique();
        $table->string('name');
        $table->string('native')->nullable();
        $table->string('dir', 3)->default('ltr');
        $table->timestampsTz();
    });

    Schema::create('currencies', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->char('code', 3)->unique();
        $table->string('name');
        $table->string('symbol')->nullable();
        $table->string('symbol_native')->nullable();
        $table->unsignedTinyInteger('precision')->default(2);
        $table->boolean('symbol_first')->nullable();
        $table->string('decimal_mark', 1)->nullable();
        $table->string('thousands_separator', 1)->nullable();
        $table->timestampsTz();
    });

    Schema::create('timezones', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name')->unique();
        $table->timestampsTz();
    });
});

it('seeds reference data in bulk and quiesces on re-run', function (string $actionClass, string $table): void {
    DB::enableQueryLog();

    $first = app($actionClass)->execute();
    $firstRunQueries = count(DB::getQueryLog());

    DB::flushQueryLog();

    $second = app($actionClass)->execute();
    $secondRunQueries = count(DB::getQueryLog());

    DB::disableQueryLog();

    expect($first['created'])->toBeGreaterThan(100)
        ->and($first['updated'])->toBe(0)
        ->and($first['skipped'])->toBe(0)
        ->and($firstRunQueries)->toBeLessThan(10)
        ->and($second['created'])->toBe(0)
        ->and($second['updated'])->toBe(0)
        ->and($second['skipped'])->toBe($first['created'])
        ->and($secondRunQueries)->toBeLessThan(10)
        ->and(DB::table($table)->count())->toBe($first['created']);
})->with([
    'currencies' => [SeedCurrenciesAction::class, 'currencies'],
    'languages' => [SeedLanguagesAction::class, 'languages'],
    'timezones' => [SeedTimezonesAction::class, 'timezones'],
]);

it('updates only changed reference rows', function (): void {
    app(SeedCurrenciesAction::class)->execute();

    DB::table('currencies')->where('code', 'USD')->update(['name' => 'Tampered']);

    $result = app(SeedCurrenciesAction::class)->execute();

    expect($result['updated'])->toBe(1)
        ->and($result['created'])->toBe(0)
        ->and(DB::table('currencies')->where('code', 'USD')->value('name'))->toBe('US Dollar');
});
