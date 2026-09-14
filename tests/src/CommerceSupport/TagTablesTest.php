<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Models\Tag;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

function runTagTablesMigration(): void
{
    /** @var Migration $migration */
    $migration = require dirname(__DIR__, 3) . '/packages/commerce-support/database/migrations/1970_01_01_000001_create_tag_tables.php.stub';

    $migration->up();
}

it('creates tag tables with a configurable morph key type', function (): void {
    Schema::dropIfExists('taggables');
    Schema::dropIfExists('tags');

    runTagTablesMigration();

    expect(Schema::hasTable('tags'))->toBeTrue()
        ->and(Schema::hasTable('taggables'))->toBeTrue();

    $idType = Schema::getColumnType('taggables', 'taggable_id');

    // Default commerce-support morph key type is uuid (SQLite reports it
    // as a string column, never as the integer morph key).
    expect($idType)->not->toBe('integer');
});

it('creates tag tables with integer morph keys when configured', function (): void {
    Schema::dropIfExists('taggables');
    Schema::dropIfExists('tags');

    Schema::defaultMorphKeyType('int');

    try {
        runTagTablesMigration();

        expect(Schema::getColumnType('taggables', 'taggable_id'))->toBe('integer');
    } finally {
        Schema::defaultMorphKeyType('uuid');
    }
});

it('resolves tag table names from commerce-support config', function (): void {
    Schema::dropIfExists('shop_taggables');
    Schema::dropIfExists('shop_tags');

    Config::set('commerce-support.database.tables.tags', 'shop_tags');
    Config::set('commerce-support.database.tables.taggables', 'shop_taggables');

    runTagTablesMigration();

    expect(Schema::hasTable('shop_tags'))->toBeTrue()
        ->and(Schema::hasTable('shop_taggables'))->toBeTrue()
        ->and((new Tag)->getTable())->toBe('shop_tags');
});
