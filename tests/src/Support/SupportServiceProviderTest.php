<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Commands\SetupCommand;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\NullOwnerResolver;
use AIArmada\CommerceSupport\SupportServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Spatie\LaravelPackageTools\Package;
use Spatie\WebhookClient\Models\WebhookCall;

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

it('registers fallback dependency migration paths when they are not published', function (): void {
    $paths = collect(app('migrator')->paths());
    $containsRuntimePath = static fn (string $suffix): bool => $paths->contains(
        static fn (string $path): bool => str_contains($path, "{$suffix}.php")
    );

    $expectsPath = function (string $suffix): bool {
        return glob(database_path("migrations/*_{$suffix}.php")) === [];
    };

    if ($expectsPath('create_settings_table')) {
        expect($containsRuntimePath('create_settings_table'))->toBeTrue();
    }

    if ($expectsPath('create_audits_table')) {
        expect($containsRuntimePath('create_audits_table'))->toBeTrue();
    }

    if ($expectsPath('create_activity_log_table')) {
        expect($containsRuntimePath('create_activity_log_table'))->toBeTrue();
    }

    if ($expectsPath('create_tag_tables')) {
        expect($containsRuntimePath('create_tag_tables'))->toBeTrue();
    }

    if ($expectsPath('create_media_table')) {
        expect($containsRuntimePath('create_media_table'))->toBeTrue();
    }

    if ($expectsPath('create_webhook_calls_table')) {
        expect($containsRuntimePath('create_webhook_calls_table'))->toBeTrue();
    }

});

it('only loads the shared webhook migration when a package registers a valid webhook config', function (): void {
    config()->set('webhook-client.configs', []);

    $provider = new SupportServiceProvider(app());
    $method = new ReflectionMethod($provider, 'shouldLoadWebhookCallsMigration');
    $method->setAccessible(true);

    expect($method->invoke($provider))->toBeFalse();

    config()->set('webhook-client.configs', [
        [
            'name' => 'support-test',
            'webhook_model' => WebhookCall::class,
            'process_webhook_job' => stdClass::class,
        ],
    ]);

    Schema::dropIfExists('webhook_calls');

    expect($method->invoke($provider))->toBeTrue();
});

class SupportTestOwnerResolver implements OwnerResolverInterface
{
    public function resolve(): ?Model
    {
        return null;
    }
}
