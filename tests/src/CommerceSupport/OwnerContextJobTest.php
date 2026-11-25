<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerScopedJob;
use AIArmada\CommerceSupport\Data\OwnerJobContext;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\OwnerContextJob;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

final class OwnerContextJobProbe implements OwnerScopedJob
{
    use OwnerContextJob;

    public function __construct(
        private readonly OwnerJobContext $context,
        private readonly Closure $work,
    ) {}

    public function ownerContext(): OwnerJobContext
    {
        return $this->context;
    }

    protected function performJob(): void
    {
        ($this->work)();
    }
}

it('runs scoped work using a persisted owner and restores the previous context', function (): void {
    $owner = User::factory()->create();
    $previous = OwnerContext::resolve();
    $executed = false;

    $job = new OwnerContextJobProbe(
        new OwnerJobContext($owner->getMorphClass(), $owner->getKey()),
        function () use ($owner, &$executed): void {
            expect(OwnerContext::resolve()?->is($owner))->toBeTrue();
            $executed = true;
        },
    );

    $job->handle();

    expect($executed)->toBeTrue()
        ->and(OwnerContext::resolve()?->getKey())->toBe($previous?->getKey());
});

it('restores the previous owner when job execution throws', function (): void {
    $owner = User::factory()->create();
    $previous = OwnerContext::resolve();

    $job = new OwnerContextJobProbe(
        new OwnerJobContext($owner->getMorphClass(), $owner->getKey()),
        fn () => throw new RuntimeException('job failed'),
    );

    expect(fn () => $job->handle())->toThrow(RuntimeException::class, 'job failed');
    expect(OwnerContext::resolve()?->getKey())->toBe($previous?->getKey());
});

it('fails closed when the job owner no longer exists', function (): void {
    $job = new OwnerContextJobProbe(
        new OwnerJobContext(User::class, (string) Str::uuid()),
        fn () => throw new LogicException('The job must not execute.'),
    );

    expect(fn () => $job->handle())->toThrow(ModelNotFoundException::class);
});

it('fails closed when the owner is unspecified and owner mode is enabled', function (): void {
    config(['commerce-support.owner.enabled' => true]);

    $job = new OwnerContextJobProbe(
        new OwnerJobContext(null, null),
        fn () => throw new LogicException('The job must not execute.'),
    );

    expect(fn () => $job->handle())->toThrow(RuntimeException::class, 'requires an owner context');
});

it('does not enter explicit global scope when owner mode is disabled', function (): void {
    config(['commerce-support.owner.enabled' => false]);
    $executed = false;

    $job = new OwnerContextJobProbe(new OwnerJobContext(null, null), function () use (&$executed): void {
        expect(OwnerContext::isExplicitGlobal())->toBeFalse();
        $executed = true;
    });

    $job->handle();

    expect($executed)->toBeTrue();
});

it('allows deliberately global work in owner mode and restores the previous scope', function (): void {
    config(['commerce-support.owner.enabled' => true]);
    $previous = OwnerContext::resolve();

    $job = new OwnerContextJobProbe(OwnerJobContext::explicitGlobal(), function (): void {
        expect(OwnerContext::resolve())->toBeNull()
            ->and(OwnerContext::isExplicitGlobal())->toBeTrue();
    });

    $job->handle();

    expect(OwnerContext::resolve()?->getKey())->toBe($previous?->getKey());
});

it('rejects invalid owner identity pairs and contradictory global declarations', function (): void {
    expect(fn () => new OwnerJobContext(User::class, null))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new OwnerJobContext(null, 'owner'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new OwnerJobContext(User::class, 'owner', true))->toThrow(InvalidArgumentException::class);
});
