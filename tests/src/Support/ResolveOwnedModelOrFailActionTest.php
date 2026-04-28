<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Actions\ResolveOwnedModelOrFailAction;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('resolve_owned_model_action_fixtures');
    Schema::create('resolve_owned_model_action_fixtures', function (Blueprint $table): void {
        $table->id();
        $table->nullableMorphs('owner');
        $table->string('label');
        $table->timestamps();
    });
});

it('resolves an owner-scoped model for the matching owner', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-resolve-owned-action@example.com',
        'password' => 'secret',
    ]);

    $ownerRecord = OwnerContext::withOwner($ownerA, fn () => ResolveOwnedModelFixture::query()->create([
        'label' => 'owner-a',
    ]));

    $resolved = ResolveOwnedModelOrFailAction::run(
        modelClass: ResolveOwnedModelFixture::class,
        id: (string) $ownerRecord->getKey(),
        owner: $ownerA,
    );

    expect($resolved->label)->toBe('owner-a');
});

it('throws for cross-owner access', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-resolve-owned-cross@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b-resolve-owned-cross@example.com',
        'password' => 'secret',
    ]);

    $ownerRecord = OwnerContext::withOwner($ownerA, fn () => ResolveOwnedModelFixture::query()->create([
        'label' => 'owner-a',
    ]));

    expect(fn () => ResolveOwnedModelOrFailAction::run(
        modelClass: ResolveOwnedModelFixture::class,
        id: (string) $ownerRecord->getKey(),
        owner: $ownerB,
    ))->toThrow(AuthorizationException::class);
});

final class ResolveOwnedModelFixture extends Model
{
    use HasOwner;

    protected $guarded = [];

    public function getTable(): string
    {
        return 'resolve_owned_model_action_fixtures';
    }
}
