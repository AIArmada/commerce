<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\CommerceSupport\Traits\HasOwner;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('owner_write_guard_fixtures');
    Schema::create('owner_write_guard_fixtures', function (Blueprint $table): void {
        $table->id();
        $table->nullableMorphs('owner');
        $table->string('label');
        $table->timestamps();
    });
});

it('resolves only records accessible to the owner context', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-write-guard@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b-write-guard@example.com',
        'password' => 'secret',
    ]);

    $ownerRecord = OwnerContext::withOwner($ownerA, fn () => OwnerWriteGuardFixture::query()->create([
        'label' => 'owner-a',
    ]));

    $globalRecord = OwnerContext::withOwner(null, fn () => OwnerWriteGuardFixture::query()->create([
        'label' => 'global',
    ]));

    $resolved = OwnerWriteGuard::findOrFailForOwner(OwnerWriteGuardFixture::class, (string) $ownerRecord->getKey(), $ownerA);

    expect($resolved->label)->toBe('owner-a');

    OwnerWriteGuard::findOrFailForOwner(OwnerWriteGuardFixture::class, (string) $globalRecord->getKey(), $ownerB, true);

    expect(fn () => OwnerWriteGuard::findOrFailForOwner(OwnerWriteGuardFixture::class, (string) $ownerRecord->getKey(), $ownerB))
        ->toThrow(AuthorizationException::class);
});

final class OwnerWriteGuardFixture extends Model
{
    use HasOwner;

    protected $guarded = [];

    public function getTable(): string
    {
        return 'owner_write_guard_fixtures';
    }
}
