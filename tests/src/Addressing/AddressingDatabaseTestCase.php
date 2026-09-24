<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Addressing;

use AIArmada\Addressing\AddressingServiceProvider;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\SupportServiceProvider;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Support\Facades\Schema;

/**
 * Slim boot for addressing database tests.
 *
 * The monorepo TestCase boots 60+ providers; addressing action, model,
 * and support tests only touch addressing and commerce-support tables,
 * so this case boots those two packages on in-memory SQLite. Owner
 * resolution mirrors the monorepo case (fixed fixture owner) because
 * address owner mode is enabled by default.
 */
abstract class AddressingDatabaseTestCase extends AddressingGeographyTestCase
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            DatabaseServiceProvider::class,
            EventServiceProvider::class,
            CacheServiceProvider::class,
            AddressingServiceProvider::class,
            SupportServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('cache.default', 'array');
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations/commerce-support');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        Schema::dropIfExists('test_owners');
        Schema::create('test_owners', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        Schema::dropIfExists('test_models');
        Schema::create('test_models', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        $owner = User::query()->create([
            'name' => 'Default Owner',
            'email' => 'default-owner@example.com',
            'password' => 'secret',
        ]);

        app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));
    }
}
