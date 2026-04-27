<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Commands\SetupCommand;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\NullOwnerResolver;
use AIArmada\CommerceSupport\SupportServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelPackageTools\Package;

it('registers the commerce setup command', function (): void {
    $provider = new SupportServiceProvider(app());
    $package = new Package;

    $provider->configurePackage($package);

    expect($package->commands)->toContain(SetupCommand::class);
});

it('binds OwnerResolverInterface using commerce-support config', function (): void {
    putenv('COMMERCE_OWNER_RESOLVER=' . SupportTestOwnerResolver::class);
    $this->refreshApplication();

    expect(app(OwnerResolverInterface::class))
        ->toBeInstanceOf(SupportTestOwnerResolver::class);

    putenv('COMMERCE_OWNER_RESOLVER');
});

it('throws when configured owner resolver is invalid', function (): void {
    putenv('COMMERCE_OWNER_RESOLVER=' . stdClass::class);

    expect(fn () => $this->refreshApplication())
        ->toThrow(InvalidArgumentException::class);

    putenv('COMMERCE_OWNER_RESOLVER');
});

it('allows the null owner resolver when commerce owner mode is disabled', function (): void {
    putenv('COMMERCE_OWNER_ENABLED=false');
    putenv('COMMERCE_OWNER_RESOLVER=' . NullOwnerResolver::class);

    try {
        $this->refreshApplication();

        expect(config('commerce-support.owner.enabled'))->toBeFalse()
            ->and(app(OwnerResolverInterface::class))->toBeInstanceOf(NullOwnerResolver::class);
    } finally {
        putenv('COMMERCE_OWNER_ENABLED');
        putenv('COMMERCE_OWNER_RESOLVER');
    }
});

it('fails closed when commerce owner mode uses the null owner resolver', function (): void {
    putenv('COMMERCE_OWNER_ENABLED=true');
    putenv('COMMERCE_OWNER_RESOLVER=' . NullOwnerResolver::class);

    try {
        expect(fn () => $this->refreshApplication())
            ->toThrow(RuntimeException::class, 'NullOwnerResolver is configured while commerce-support owner mode is enabled');
    } finally {
        putenv('COMMERCE_OWNER_ENABLED');
        putenv('COMMERCE_OWNER_RESOLVER');
    }
});

class SupportTestOwnerResolver implements OwnerResolverInterface
{
    public function resolve(): ?Model
    {
        return null;
    }
}
