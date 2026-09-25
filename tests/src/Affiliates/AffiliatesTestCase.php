<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Affiliates;

use AIArmada\Affiliates\AffiliatesServiceProvider;
use AIArmada\Cart\CartServiceProvider;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\SupportServiceProvider as CommerceSupportServiceProvider;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\Contacting\ContactingServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;

/**
 * Slim case for the affiliates-engine suite: six providers and four
 * migration paths instead of the monorepo-wide base case. Only four test
 * files reach outside the engine (cart storage/registry, order rows,
 * voucher DTOs), so only those migrations ride along. Media,
 * notifications, and permission tables are intentionally absent.
 */
abstract class AffiliatesTestCase extends Orchestra
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../../packages/affiliates/database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../../../packages/contacting/database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../../../packages/cart/database/migrations');
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

        Schema::dropIfExists('test_products');
        Schema::create('test_products', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
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
        $app['config']->set('cart.money.default_currency', 'USD');

        $app['config']->set('data.date_format', DATE_ATOM);
        $app['config']->set('data.date_timezone', null);
        $app['config']->set('data.max_transformation_depth', 512);
        $app['config']->set('data.throw_when_max_transformation_depth_reached', true);

        $app['config']->set('affiliates.owner.enabled', false);
        $app['config']->set('affiliates.owner.include_global', false);
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelDataServiceProvider::class,
            LaravelSettingsServiceProvider::class,
            CommerceSupportServiceProvider::class,
            ContactingServiceProvider::class,
            CartServiceProvider::class,
            AffiliatesServiceProvider::class,
        ];
    }
}
