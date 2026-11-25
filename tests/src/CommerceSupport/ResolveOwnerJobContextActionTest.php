<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Actions\ResolveOwnerJobContextAction;
use AIArmada\CommerceSupport\Contracts\OwnerScopedJob;
use AIArmada\CommerceSupport\Contracts\OwnerScopeIdentifiable;
use AIArmada\CommerceSupport\Support\OwnerJobContext;
use Illuminate\Database\Eloquent\Model;

it('uses explicit owner context from owner scoped job contract', function (): void {
    $job = new class implements OwnerScopedJob
    {
        public function ownerContext(): OwnerJobContext
        {
            return new OwnerJobContext(
                ownerType: 'contract-owner-type',
                ownerId: 'contract-owner',
                ownerIsGlobal: false,
            );
        }
    };

    $resolved = ResolveOwnerJobContextAction::run(job: $job);

    expect($resolved->ownerType)->toBe('contract-owner-type')
        ->and((string) $resolved->ownerId)->toBe('contract-owner')
        ->and($resolved->ownerIsGlobal)->toBeFalse();
});

it('resolves owner context from explicit payload fields', function (): void {
    $job = new class
    {
        public string $ownerType = 'payload-owner-type';

        public string $ownerId = 'payload-owner';
    };

    $resolved = ResolveOwnerJobContextAction::run(job: $job);

    expect($resolved->ownerType)->toBe('payload-owner-type')
        ->and((string) $resolved->ownerId)->toBe('payload-owner')
        ->and($resolved->ownerIsGlobal)->toBeFalse();
});

it('resolves owner context from a model owner tuple', function (): void {
    $payload = new ResolveOwnerJobContextActionOwnedFixture;
    $payload->owner_type = 'tuple-owner-type';
    $payload->owner_id = 'tuple-owner';

    $job = new class($payload)
    {
        public function __construct(public Model $payload) {}
    };

    $resolved = ResolveOwnerJobContextAction::run(job: $job);

    expect($resolved->ownerType)->toBe('tuple-owner-type')
        ->and((string) $resolved->ownerId)->toBe('tuple-owner')
        ->and($resolved->ownerIsGlobal)->toBeFalse();
});

it('resolves owner context from a model getOwner relationship', function (): void {
    $owner = new ResolveOwnerJobContextActionOwnerModel;
    $owner->setAttribute($owner->getKeyName(), 'related-owner');

    $payload = new ResolveOwnerJobContextActionOwnerByRelationFixture;
    $payload->owner = $owner;

    $job = new class($payload)
    {
        public function __construct(public Model $payload) {}
    };

    $resolved = ResolveOwnerJobContextAction::run(job: $job);

    expect($resolved->ownerType)->toBe($owner->getMorphClass())
        ->and((string) $resolved->ownerId)->toBe('related-owner')
        ->and($resolved->ownerIsGlobal)->toBeFalse();
});

it('treats an owner scope identifiable property as the owner', function (): void {
    $reference = new ResolveOwnerJobContextActionOwnerReference('identifiable-owner');

    $job = new class($reference)
    {
        public function __construct(public ResolveOwnerJobContextActionOwnerReference $ownerReference) {}
    };

    $resolved = ResolveOwnerJobContextAction::run(job: $job);

    expect($resolved->ownerType)->toBe('test-owner-type')
        ->and((string) $resolved->ownerId)->toBe('identifiable-owner')
        ->and($resolved->ownerIsGlobal)->toBeFalse();
});

it('never treats arbitrary model payloads as the tenant owner', function (): void {
    $payload = new ResolveOwnerJobContextActionPlainFixture;

    $job = new class($payload)
    {
        public function __construct(public Model $payload) {}
    };

    $resolved = ResolveOwnerJobContextAction::run(job: $job);

    expect($resolved->ownerType)->toBeNull()
        ->and($resolved->ownerId)->toBeNull()
        ->and($resolved->ownerIsGlobal)->toBeFalse();
});

it('never treats a global owner tuple as the tenant owner', function (): void {
    $payload = new ResolveOwnerJobContextActionOwnedFixture;
    $payload->owner_type = null;
    $payload->owner_id = null;

    $job = new class($payload)
    {
        public function __construct(public Model $payload) {}
    };

    $resolved = ResolveOwnerJobContextAction::run(job: $job);

    expect($resolved->ownerType)->toBeNull()
        ->and($resolved->ownerId)->toBeNull()
        ->and($resolved->ownerIsGlobal)->toBeFalse();
});

it('prefers explicit payload fields over model properties regardless of declaration order', function (): void {
    $payload = new ResolveOwnerJobContextActionOwnedFixture;
    $payload->owner_type = 'model-owner-type';
    $payload->owner_id = 'model-owner-id';

    $job = new class($payload)
    {
        public string $ownerType = 'explicit-owner-type';

        public string $ownerId = 'explicit-owner';

        public function __construct(public Model $payload) {}
    };

    $resolved = ResolveOwnerJobContextAction::run(job: $job);

    expect($resolved->ownerType)->toBe('explicit-owner-type')
        ->and((string) $resolved->ownerId)->toBe('explicit-owner');
});

it('returns an empty context when no owner source exists', function (): void {
    $job = new class
    {
        public ?string $ownerType = null;

        public ?string $ownerId = null;

        public bool $ownerIsGlobal = false;

        public string $payload = 'plain';
    };

    $resolved = ResolveOwnerJobContextAction::run(job: $job);

    expect($resolved->ownerType)->toBeNull()
        ->and($resolved->ownerId)->toBeNull()
        ->and($resolved->ownerIsGlobal)->toBeFalse();
});

it('throws runtime exception for contradictory explicit-global payload', function (): void {
    $job = new class
    {
        public bool $ownerIsGlobal = true;

        public string $ownerType = 'contradictory-type';

        public string $ownerId = 'contradictory';
    };

    expect(fn () => ResolveOwnerJobContextAction::run(job: $job))
        ->toThrow(RuntimeException::class, 'received invalid owner job context payload');
});

it('throws runtime exception when only half of an owner tuple is present', function (): void {
    $job = new class
    {
        public string $ownerType = 'half-tuple-type';
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

final class ResolveOwnerJobContextActionOwnerReference implements OwnerScopeIdentifiable
{
    public function __construct(private readonly string $key) {}

    public function getMorphClass(): string
    {
        return 'test-owner-type';
    }

    public function getKey(): string
    {
        return $this->key;
    }
}

final class ResolveOwnerJobContextActionOwnedFixture extends Model
{
    public $timestamps = false;
}

final class ResolveOwnerJobContextActionPlainFixture extends Model
{
    public $timestamps = false;
}

final class ResolveOwnerJobContextActionOwnerByRelationFixture extends Model
{
    public $timestamps = false;

    public ?Model $owner = null;

    public function getOwner(): ?Model
    {
        return $this->owner;
    }
}
