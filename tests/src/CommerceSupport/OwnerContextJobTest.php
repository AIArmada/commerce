<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\OwnerContextJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Queue\SerializesModels;

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

    it('enters owner context from public model property', function (): void {
        $owner = User::query()->create([
            'name' => 'Job Owner',
            'email' => 'job-owner@example.com',
            'password' => 'secret',
        ]);

        $contextInJob = null;

        $job = new class($owner, $contextInJob)
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
        $owner = User::query()->create([
            'name' => 'Job Restore Owner',
            'email' => 'job-restore-owner@example.com',
            'password' => 'secret',
        ]);

        $job = new class($owner)
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

    it('succeeds with null owner when mode disabled', function (): void {
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
            }

            public function performJob(): void
            {
                $this->executed = true;
            }
        };

        $job->handle();

        expect($executed)->toBeTrue();
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
