<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\OwnerContextJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Queue\SerializesModels;

function ownerContextJobOwner(string $name): User
{
    return User::query()->create([
        'name' => $name,
        'email' => $name . '@example.com',
        'password' => 'secret',
    ]);
}

function ownerContextJobPayload(Model $owner): OwnerContextJobPayloadFixture
{
    $payload = new OwnerContextJobPayloadFixture;
    $payload->owner = $owner;

    return $payload;
}

describe('OwnerContextJob', function (): void {
    it('executes performJob hook', function (): void {
        $executed = false;

        $job = new class($executed)
        {
            use OwnerContextJob;
            use SerializesModels;

            private $executed;

            public function __construct(&$exec)
            {
                $this->executed = &$exec;
            }

            public function performJob(): void
            {
                $this->executed = true;
            }
        };

        $job->handle();

        expect($executed)->toBeTrue();
    });

    it('enters owner context from a model property exposing getOwner', function (): void {
        $owner = ownerContextJobOwner('job-owner');

        $contextInJob = null;

        $job = new class(ownerContextJobPayload($owner), $contextInJob)
        {
            use OwnerContextJob;
            use SerializesModels;

            public function __construct(public $storeModel, private &$ctx) {}

            public function performJob(): void
            {
                $this->ctx = OwnerContext::resolve();
            }
        };

        $job->handle();

        expect($contextInJob)->not->toBeNull()
            ->and($contextInJob->getMorphClass())->toBe($owner->getMorphClass())
            ->and($contextInJob->getKey())->toBe($owner->getKey());
    });

    it('restores previous context after job', function (): void {
        $owner = ownerContextJobOwner('job-restore-owner');

        $job = new class(ownerContextJobPayload($owner))
        {
            use OwnerContextJob;
            use SerializesModels;

            public function __construct(public $storeModel) {}

            public function performJob(): void {}
        };

        $before = OwnerContext::resolve();
        $job->handle();
        $after = OwnerContext::resolve();

        expect($before)->toBe($after);
    });

    it('ignores plain model properties that are not owner references', function (): void {
        config(['commerce-support.owner.enabled' => false]);

        $executed = false;

        $job = new class($executed)
        {
            use OwnerContextJob;
            use SerializesModels;

            private $executed;

            public function __construct(&$exec)
            {
                $this->executed = &$exec;

                $this->payloadModel = new OwnerContextJobPayloadFixture;
            }

            public $payloadModel;

            public function performJob(): void
            {
                $this->executed = true;
            }
        };

        $job->handle();

        expect($executed)->toBeTrue();
    });

    it('fails closed when the job has no resolvable owner and owner mode is enabled', function (): void {
        config(['commerce-support.owner.enabled' => true]);

        $job = new class
        {
            use OwnerContextJob;
            use SerializesModels;

            public $payloadModel;

            public function __construct()
            {
                $this->payloadModel = new OwnerContextJobPayloadFixture;
            }

            public function performJob(): void {}
        };

        expect(fn () => $job->handle())
            ->toThrow(RuntimeException::class, 'requires an owner context');
    });

    it('resolves owner from explicit ownerType and ownerId payload fields', function (): void {
        $contextInJob = null;

        $owner = User::query()->create([
            'name' => 'Explicit Payload Owner',
            'email' => 'explicit-payload-owner@example.com',
            'password' => 'secret',
        ]);

        $job = new class($contextInJob, $owner)
        {
            use OwnerContextJob;
            use SerializesModels;

            public string $ownerType;

            public int | string $ownerId;

            public function __construct(private &$ctx, User $owner)
            {
                $this->ownerType = $owner::class;
                $this->ownerId = $owner->getKey();
            }

            public function performJob(): void
            {
                $this->ctx = OwnerContext::resolve();
            }
        };

        $job->handle();

        expect($contextInJob)->not->toBeNull()
            ->and($contextInJob)->toBeInstanceOf(Model::class)
            ->and($contextInJob->getKey())->toBe($owner->getKey());
    });

    it('fails when the job owner no longer exists', function (): void {
        $job = new class
        {
            use OwnerContextJob;
            use SerializesModels;

            public string $ownerType = User::class;

            public int $ownerId = 999999;

            public function performJob(): void {}
        };

        expect(fn () => $job->handle())->toThrow(ModelNotFoundException::class);
    });

    it('throws when owner missing and owner mode enabled', function (): void {
        config(['commerce-support.owner.enabled' => true]);

        $job = new class
        {
            use OwnerContextJob;
            use SerializesModels;

            public function performJob(): void {}
        };

        expect(fn () => $job->handle())
            ->toThrow(RuntimeException::class, 'requires an owner context');
    });

    it('succeeds with null owner when mode disabled without activating explicit global', function (): void {
        config(['commerce-support.owner.enabled' => false]);

        $explicitGlobalInJob = 'uninitialized';

        $job = new class($explicitGlobalInJob)
        {
            use OwnerContextJob;
            use SerializesModels;

            public function __construct(private &$explicitGlobal) {}

            public function performJob(): void
            {
                $this->explicitGlobal = OwnerContext::isExplicitGlobal();
            }
        };

        $job->handle();

        expect($explicitGlobalInJob)->toBeFalse();
    });

    it('allows explicit global execution when owner mode is enabled', function (): void {
        config(['commerce-support.owner.enabled' => true]);

        $contextInJob = 'uninitialized';

        $job = new class($contextInJob)
        {
            use OwnerContextJob;
            use SerializesModels;

            public bool $ownerIsGlobal = true;

            public function __construct(private &$ctx) {}

            public function performJob(): void
            {
                $this->ctx = OwnerContext::resolve();
            }
        };

        $job->handle();

        expect($contextInJob)->toBeNull();
    });

    it('throws on contradictory explicit-global owner payload', function (): void {
        config(['commerce-support.owner.enabled' => true]);

        $owner = new class extends Model
        {
            public $timestamps = false;

            public $incrementing = false;

            protected $keyType = 'string';
        };

        $job = new class($owner::class)
        {
            use OwnerContextJob;
            use SerializesModels;

            public bool $ownerIsGlobal = true;

            public string $ownerType;

            public string $ownerId = 'ctx-contradictory';

            public function __construct(string $ownerType)
            {
                $this->ownerType = $ownerType;
            }

            public function performJob(): void {}
        };

        expect(fn () => $job->handle())
            ->toThrow(RuntimeException::class, 'ownerIsGlobal=true cannot be combined');
    });
});

final class OwnerContextJobPayloadFixture extends Model
{
    public $timestamps = false;

    public ?Model $owner = null;

    public function getOwner(): ?Model
    {
        return $this->owner;
    }
}
