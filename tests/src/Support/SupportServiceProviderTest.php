<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Commands\SetupCommand;
use AIArmada\CommerceSupport\SupportServiceProvider;
use Spatie\LaravelPackageTools\Package;

it('registers the commerce setup command', function (): void {
    $provider = new SupportServiceProvider(app());
    $package = new Package;

    $provider->configurePackage($package);

    expect($package->commands)->toContain(SetupCommand::class);
});
