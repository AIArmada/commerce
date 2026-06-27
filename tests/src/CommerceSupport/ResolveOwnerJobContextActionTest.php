<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Actions\ResolveOwnerJobContextAction;
use AIArmada\CommerceSupport\Contracts\OwnerScopedJob;
use AIArmada\CommerceSupport\Support\OwnerJobContext;
use Illuminate\Database\Eloquent\Model;

it('uses explicit owner context from owner scoped job contract', function (): void {
    $job = new class implements OwnerScopedJob
    {
        public function ownerContext(): OwnerJobContext
        {
            return new OwnerJobContext(
                ownerType: ResolveOwnerJobContextActionOwnerModel::class,
                ownerId: 'contract-owner',
                ownerIsGlobal: false,
            );
        }
    };

    $resolved = ResolveOwnerJobContextAction::run(job: $job);

    expect($resolved->ownerType)->toBe(ResolveOwnerJobContextActionOwnerModel::class)
        ->and((string) $resolved->ownerId)->toBe('contract-owner')
        ->and($resolved->ownerIsGlobal)->toBeFalse();
});

it('resolves owner context from explicit payload fields', function (): void {
    $job = new class
    {
        public string $ownerType = ResolveOwnerJobContextActionOwnerModel::class;

        public string $ownerId = 'payload-owner';
    };

    $resolved = ResolveOwnerJobContextAction::run(job: $job);

    expect($resolved->ownerType)->toBe(ResolveOwnerJobContextActionOwnerModel::class)
        ->and((string) $resolved->ownerId)->toBe('payload-owner')
        ->and($resolved->ownerIsGlobal)->toBeFalse();
});

it('throws runtime exception for contradictory explicit-global payload', function (): void {
    $job = new class
    {
        public bool $ownerIsGlobal = true;

        public string $ownerType = ResolveOwnerJobContextActionOwnerModel::class;

        public string $ownerId = 'contradictory';
    };

    expect(fn () => ResolveOwnerJobContextAction::run(job: $job))
        ->toThrow(RuntimeException::class, 'received invalid owner job context payload');
});

final class ResolveOwnerJobContextActionOwnerModel extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';
}
