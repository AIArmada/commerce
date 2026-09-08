<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeKey;
use AIArmada\Customers\Models\Segment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

require_once __DIR__ . '/Fixtures/CustomersTestOwner.php';

beforeEach(function (): void {
    Schema::dropIfExists('test_owners');
    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });
});

it('enforces deterministic global customer segment uniqueness', function (): void {
    config()->set('customers.features.owner.enabled', true);

    OwnerContext::withOwner(null, function (): void {
        Segment::query()->create(['name' => 'Global VIP', 'slug' => 'global-vip']);
        expect(fn () => Segment::query()->create(['name' => 'Global VIP 2', 'slug' => 'global-vip']))
            ->toThrow(ValidationException::class);
        expect(Segment::query()->globalOnly()->where('slug', 'global-vip')->sole()->owner_scope)
            ->toBe(OwnerScopeKey::GLOBAL);
    });
});

it('allows the same segment slug for different owner tuples', function (): void {
    config()->set('customers.features.owner.enabled', true);

    $ownerA = CustomersTestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = CustomersTestOwner::query()->create(['name' => 'Owner B']);

    $segmentA = OwnerContext::withOwner($ownerA, fn (): Segment => Segment::query()->create([
        'name' => 'Shared Slug A',
        'slug' => 'shared-segment',
    ]));
    $segmentB = OwnerContext::withOwner($ownerB, fn (): Segment => Segment::query()->create([
        'name' => 'Shared Slug B',
        'slug' => 'shared-segment',
    ]));

    expect($segmentA->slug)->toBe($segmentB->slug)
        ->and($segmentA->owner_scope)->not->toBe($segmentB->owner_scope)
        ->and(OwnerContext::withOwner($ownerA, fn (): bool => Segment::query()->where('slug', 'shared-segment')->exists()))->toBeTrue()
        ->and(OwnerContext::withOwner($ownerB, fn (): bool => Segment::query()->where('slug', 'shared-segment')->exists()))->toBeTrue();
});
