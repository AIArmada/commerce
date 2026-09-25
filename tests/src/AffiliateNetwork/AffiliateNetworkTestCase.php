<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\AffiliateNetwork;

use AIArmada\AffiliateNetwork\AffiliateNetworkServiceProvider;
use AIArmada\Affiliates\AffiliatesServiceProvider;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\SupportServiceProvider as CommerceSupportServiceProvider;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\Contacting\ContactingServiceProvider;
use AIArmada\Links\LinksServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\LaravelData\LaravelDataServiceProvider;

/**
 * Slim case for the affiliate-network suite: six providers and five
 * migration paths instead of the monorepo-wide base case. Tables and
 * bindings mirror the base case's network-relevant subset (users table,
 * ambient default owner); media, notifications, and permission tables
 * are intentionally absent — nothing under src/AffiliateNetwork uses them.
 */
abstract class AffiliateNetworkTestCase extends Orchestra
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../../packages/affiliate-network/database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../../../packages/affiliates/database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../../../packages/links/database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../../../packages/contacting/database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../../../packages/orders/database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Reset any leaked static unguarded state from a previous test so every
        // test starts at the framework default (guarded). Mirrors the base case.
        Model::reguard();

        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        $owner = User::query()->create([
            'name' => 'Default Owner',
            'email' => 'default-owner@example.com',
            'password' => 'secret',
        ]);

        app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('app.env', 'testing');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');

        $app['config']->set('auth.providers.users.model', User::class);

        $app['config']->set('data.date_format', DATE_ATOM);
        $app['config']->set('data.date_timezone', null);
        $app['config']->set('data.max_transformation_depth', 512);
        $app['config']->set('data.throw_when_max_transformation_depth_reached', true);

        $app['config']->set('affiliate-network.owner.enabled', false);
        $app['config']->set('affiliate-network.owner.include_global', false);
        $app['config']->set('affiliates.owner.enabled', false);
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelDataServiceProvider::class,
            CommerceSupportServiceProvider::class,
            ContactingServiceProvider::class,
            LinksServiceProvider::class,
            AffiliatesServiceProvider::class,
            AffiliateNetworkServiceProvider::class,
        ];
    }
}
