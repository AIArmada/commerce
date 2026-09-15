<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\UniqueSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('unique_slug_probe');

    Schema::create('unique_slug_probe', function (Blueprint $table): void {
        $table->id();
        $table->string('slug')->nullable();
        $table->string('tenant')->nullable();
    });

    $this->probe = new class extends Model
    {
        protected $table = 'unique_slug_probe';

        public $timestamps = false;

        protected $guarded = [];
    };
});

it('returns the base slug when nothing collides', function (): void {
    expect(UniqueSlug::build($this->probe::class, 'my-event'))->toBe('my-event');
});

it('appends a numeric suffix on collisions', function (): void {
    $class = $this->probe::class;
    $class::query()->create(['slug' => 'my-event']);
    $class::query()->create(['slug' => 'my-event-2']);

    expect(UniqueSlug::build($class, 'my-event'))->toBe('my-event-3');
});

it('places middle segments before the sequence and the trailing suffix last', function (): void {
    $class = $this->probe::class;
    $class::query()->create(['slug' => 'my-event-kuala-lumpur-my']);

    expect(UniqueSlug::build($class, 'my-event', ['kuala-lumpur'], 'my'))
        ->toBe('my-event-kuala-lumpur-2-my');
});

it('ignores blank middle segments', function (): void {
    expect(UniqueSlug::build($this->probe::class, 'my-event', ['', 'hall-a'], ''))
        ->toBe('my-event-hall-a');
});

it('excludes the ignored key from the collision check', function (): void {
    $class = $this->probe::class;
    $own = $class::query()->create(['slug' => 'my-event']);
    $class::query()->create(['slug' => 'my-event-2']);

    expect(UniqueSlug::build($class, 'my-event', ignoreKey: $own->getKey()))
        ->toBe('my-event');
});

it('truncates candidates to the maximum slug length', function (): void {
    $base = str_repeat('a', 250);

    $slug = UniqueSlug::build($this->probe::class, $base);

    expect($slug)->toBe(str_repeat('a', 200));
});

it('rejects an empty base slug', function (): void {
    expect(fn () => UniqueSlug::build($this->probe::class, ''))->toThrow(InvalidArgumentException::class);
});

it('detects collisions hidden by truncation instead of returning a duplicate', function (): void {
    $class = $this->probe::class;
    $base = str_repeat('a', 250);
    $class::query()->create(['slug' => str_repeat('a', 200)]);

    $slug = UniqueSlug::build($class, $base);

    expect($slug)->not->toBe(str_repeat('a', 200))
        ->and(mb_strlen($slug))->toBeLessThanOrEqual(200);

    $class::query()->create(['slug' => $slug]);

    expect(UniqueSlug::build($class, $base))
        ->not->toBe(str_repeat('a', 200))
        ->not->toBe($slug);
});

it('respects global scopes unless explicitly bypassed', function (): void {
    $scoped = new class extends Model
    {
        protected $table = 'unique_slug_probe';

        public $timestamps = false;

        protected $guarded = [];

        protected static function booted(): void
        {
            static::addGlobalScope('tenant-a', fn ($query): mixed => $query->where('tenant', 'a'));
        }
    };

    $scoped::query()->withoutGlobalScopes()->create(['slug' => 'my-event', 'tenant' => 'b']);

    expect(UniqueSlug::build($scoped::class, 'my-event'))->toBe('my-event')
        ->and(UniqueSlug::build($scoped::class, 'my-event', withoutGlobalScopes: true))->toBe('my-event-2');
});
