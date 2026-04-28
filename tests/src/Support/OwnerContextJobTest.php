<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\OwnerContextJob;
use Illuminate\Database\Eloquent\Model;
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
        $owner = new class extends Model
        {
            public $timestamps = false;

            public function getMorphClass(): string
            {
                return 'store';
            }

            public function getKey(): mixed
            {
                return 'ctx-123';
            }
        };

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
            ->and($contextInJob->getMorphClass())->toBe('store')
            ->and($contextInJob->getKey())->toBe('ctx-123');
    });

    it('restores previous context after job', function (): void {
        $owner = new class extends Model
        {
            public $timestamps = false;

            public function getMorphClass(): string
            {
                return 'store';
            }

            public function getKey(): mixed
            {
                return 'test';
            }
        };

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

    it('resolves owner from explicit owner_type and owner_id payload fields', function (): void {
        $contextInJob = null;
        $owner = new class extends Model
        {
            public $timestamps = false;

            public $incrementing = false;

            protected $keyType = 'string';
        };

        $job = new class($contextInJob, $owner::class)
        {
            use OwnerContextJob;
            use SerializesModels;

            public string $owner_type;

            public string $owner_id = 'ctx-explicit';

            public function __construct(private &$ctx, string $ownerType)
            {
                $this->owner_type = $ownerType;
            }

            public function performJob(): void
            {
                $this->ctx = OwnerContext::resolve();
            }
        };

        $job->handle();

        expect($contextInJob)->not->toBeNull()
            ->and($contextInJob)->toBeInstanceOf(Model::class)
            ->and((string) $contextInJob->getKey())->toBe('ctx-explicit');
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
});
