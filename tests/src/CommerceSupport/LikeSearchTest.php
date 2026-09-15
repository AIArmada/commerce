<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\LikeSearch;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;

beforeEach(function (): void {
    Schema::dropIfExists('like_search_probe');

    Schema::create('like_search_probe', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });

    DB::table('like_search_probe')->insert([
        ['name' => '100% cotton'],
        ['name' => '1000 thread cotton'],
        ['name' => 'a_b test'],
        ['name' => 'axb mark'],
        ['name' => 'plain'],
    ]);
});

it('escapes like wildcards for literal matching', function (): void {
    expect(LikeSearch::escape('100%'))->toBe('100\\%')
        ->and(LikeSearch::escape('a_b'))->toBe('a\\_b')
        ->and(LikeSearch::escape('back\\slash'))->toBe('back\\\\slash')
        ->and(LikeSearch::escape('plain'))->toBe('plain');
});

it('builds contains, prefix, and suffix patterns', function (): void {
    expect(LikeSearch::contains('100%'))->toBe('%100\\%%')
        ->and(LikeSearch::startsWith('a_b'))->toBe('a\\_b%')
        ->and(LikeSearch::endsWith('100%'))->toBe('%100\\%');
});

it('matches escaped percent signs literally on sqlite', function (): void {
    $names = LikeSearch::whereLike(DB::table('like_search_probe'), 'name', LikeSearch::contains('100%'))
        ->pluck('name')
        ->all();

    expect($names)->toBe(['100% cotton']);
});

it('matches escaped underscores literally on sqlite', function (): void {
    $names = LikeSearch::whereLike(DB::table('like_search_probe'), 'name', LikeSearch::contains('a_b'))
        ->pluck('name')
        ->all();

    expect($names)->toBe(['a_b test']);
});

it('supports or-chained like predicates', function (): void {
    $names = LikeSearch::whereLike(DB::table('like_search_probe'), 'name', LikeSearch::contains('plain'));
    $names = LikeSearch::orWhereLike($names, 'name', LikeSearch::contains('100%'));

    expect($names->pluck('name')->sort()->values()->all())->toBe(['100% cotton', 'plain']);
});

it('matches prefixes and suffixes on sqlite', function (): void {
    $prefixed = LikeSearch::whereLike(DB::table('like_search_probe'), 'name', LikeSearch::startsWith('100%'))
        ->pluck('name')
        ->all();

    $suffixed = LikeSearch::whereLike(DB::table('like_search_probe'), 'name', LikeSearch::endsWith('cotton'))
        ->pluck('name')
        ->sort()
        ->values()
        ->all();

    expect($prefixed)->toBe(['100% cotton'])
        ->and($suffixed)->toBe(['100% cotton', '1000 thread cotton']);
});

it('emits a single-backslash escape literal on sqlite', function (): void {
    $sql = LikeSearch::whereLike(DB::table('like_search_probe'), 'name', LikeSearch::contains('100%'))->toSql();

    expect($sql)->toContain("ESCAPE '\\'")
        ->and($sql)->not->toContain("ESCAPE '\\\\'");
});

it('emits a doubled-backslash escape literal on mysql', function (): void {
    $builder = new QueryBuilder(new MySqlConnection(new PDO('sqlite::memory:'), '', '', ['driver' => 'mysql']));

    $sql = LikeSearch::whereLike($builder->from('like_search_probe'), 'name', LikeSearch::contains('100%'))->toSql();

    // MySQL string literals swallow a lone backslash, so ESCAPE '\' would be
    // an unterminated literal there; the doubled form is a single backslash.
    expect($sql)->toContain("ESCAPE '\\\\'");
});

it('emits ilike with a single-backslash escape literal on pgsql', function (): void {
    $builder = new QueryBuilder(new PostgresConnection(new PDO('sqlite::memory:'), '', '', ['driver' => 'pgsql']));

    $sql = LikeSearch::whereLike($builder->from('like_search_probe'), 'name', LikeSearch::contains('100%'))->toSql();

    expect($sql)->toContain('ILIKE')
        ->and($sql)->toContain("ESCAPE '\\'")
        ->and($sql)->not->toContain("ESCAPE '\\\\'");
});

it('exposes the driver-aware escape clause for expression predicates', function (): void {
    expect(LikeSearch::escapeClause(DB::connection()))->toBe("ESCAPE '\\'");
});
