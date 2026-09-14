<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\LikeSearch;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
