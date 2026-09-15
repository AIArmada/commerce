<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\SlugRedirectRecorder;
use AIArmada\CommerceSupport\Support\CanonicalSlug;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('canonical_slug_probe');

    Schema::create('canonical_slug_probe', function (Blueprint $table): void {
        $table->id();
        $table->string('slug')->nullable();
        $table->timestamps();
    });

    $this->probe = new class extends Model
    {
        protected $table = 'canonical_slug_probe';

        protected $guarded = [];
    };

    $this->recorder = new class implements SlugRedirectRecorder
    {
        /** @var array<int, array{mixed, ?string}> */
        public array $calls = [];

        public bool $result = true;

        public function record(Model $model, ?string $previousSlug): bool
        {
            $this->calls[] = [$model->getKey(), $previousSlug];

            return $this->result;
        }
    };
});

it('persists a changed slug and records the previous one', function (): void {
    $class = $this->probe::class;
    $record = $class::query()->create(['slug' => 'old-slug']);

    $changed = CanonicalSlug::persist($record, 'new-slug', $this->recorder);

    expect($changed)->toBeTrue()
        ->and($record->fresh()->getAttribute('slug'))->toBe('new-slug')
        ->and($this->recorder->calls)->toBe([[$record->getKey(), 'old-slug']]);
});

it('does nothing when the slug is unchanged', function (): void {
    $class = $this->probe::class;
    $record = $class::query()->create(['slug' => 'same-slug']);

    $changed = CanonicalSlug::persist($record, '  same-slug  ', $this->recorder);

    expect($changed)->toBeFalse()
        ->and($this->recorder->calls)->toBe([]);
});

it('discards unsaved slug edits before comparing', function (): void {
    $class = $this->probe::class;
    $record = $class::query()->create(['slug' => 'stored-slug']);
    $record->forceFill(['slug' => 'dirty-edit']);

    $changed = CanonicalSlug::persist($record, 'stored-slug', $this->recorder);

    expect($changed)->toBeFalse()
        ->and($record->getAttribute('slug'))->toBe('stored-slug')
        ->and($this->recorder->calls)->toBe([]);
});

it('persists slugs on new records', function (): void {
    $record = new ($this->probe::class);

    $changed = CanonicalSlug::persist($record, 'first-slug', $this->recorder);

    expect($changed)->toBeTrue()
        ->and($record->exists)->toBeTrue()
        ->and($record->getAttribute('slug'))->toBe('first-slug')
        ->and($this->recorder->calls)->toBe([[$record->getKey(), null]]);
});

it('preserves timestamps when persisting', function (): void {
    $class = $this->probe::class;
    $record = $class::query()->create(['slug' => 'old-slug']);
    $record->forceFill(['updated_at' => CarbonImmutable::parse('2024-01-01 00:00:00')])->saveQuietly();

    $changed = CanonicalSlug::persist($record->fresh(), 'new-slug', $this->recorder);

    expect($changed)->toBeTrue()
        ->and($record->fresh()->getAttribute('slug'))->toBe('new-slug')
        ->and($record->fresh()->updated_at->toDateTimeString())->toBe('2024-01-01 00:00:00');
});

it('propagates the recorder result and syncs caller-changed slugs', function (): void {
    $this->recorder->result = false;
    $class = $this->probe::class;
    $record = $class::query()->create(['slug' => 'old-slug']);

    expect(CanonicalSlug::persist($record, 'new-slug', $this->recorder))->toBeFalse()
        ->and(CanonicalSlug::syncChanged($record, '  old-slug  ', $this->recorder))->toBeFalse()
        ->and($this->recorder->calls)->toHaveCount(2);
});
