<?php

declare(strict_types=1);

namespace AIArmada\Chip\Tests;

use AIArmada\Chip\ChipServiceProvider;
use AIArmada\CommerceSupport\SupportServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\LaravelData\LaravelDataServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();

        config()->set('chip.owner.enabled', false);
        config()->set('chip.owner.include_global', false);
        config()->set('chip.webhooks.verify_signature', false);
        config()->set('chip.webhooks.store_webhooks', true);
        config()->set('queue.default', 'sync');
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelDataServiceProvider::class,
            SupportServiceProvider::class,
            ChipServiceProvider::class,
        ];
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
        $app['config']->set('data.date_format', DATE_ATOM);
        $app['config']->set('data.date_timezone', null);
        $app['config']->set('chip.collect.api_key', 'test_secret_key');
        $app['config']->set('chip.collect.brand_id', 'test_brand_id');
        $app['config']->set('chip.collect.environment', 'sandbox');
        $app['config']->set('chip.send.api_key', 'test_api_key');
        $app['config']->set('chip.send.api_secret', 'test_send_secret');
        $app['config']->set('chip.webhooks.company_public_key', 'test_public_key');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function setUpDatabase(): void
    {
        if (! Schema::hasTable('webhook_calls')) {
            Schema::create('webhook_calls', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('url', 512);
                $table->json('headers')->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->text('exception')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('webhook_calls', function (Blueprint $table): void {
            if (! Schema::hasColumn('webhook_calls', 'event_type')) {
                $table->string('event_type')->nullable();
            }
            if (! Schema::hasColumn('webhook_calls', 'event')) {
                $table->string('event')->nullable();
            }
            if (! Schema::hasColumn('webhook_calls', 'status')) {
                $table->string('status')->nullable();
            }
            if (! Schema::hasColumn('webhook_calls', 'verified')) {
                $table->boolean('verified')->default(false);
            }
            if (! Schema::hasColumn('webhook_calls', 'processed')) {
                $table->boolean('processed')->default(false);
            }
            if (! Schema::hasColumn('webhook_calls', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable();
            }
            if (! Schema::hasColumn('webhook_calls', 'title')) {
                $table->string('title')->nullable();
            }
            if (! Schema::hasColumn('webhook_calls', 'events')) {
                $table->json('events')->nullable();
            }
            if (! Schema::hasColumn('webhook_calls', 'callback')) {
                $table->string('callback', 512)->nullable();
            }
            if (! Schema::hasColumn('webhook_calls', 'created_on')) {
                $table->bigInteger('created_on')->nullable();
            }
            if (! Schema::hasColumn('webhook_calls', 'updated_on')) {
                $table->bigInteger('updated_on')->nullable();
            }
            if (! Schema::hasColumn('webhook_calls', 'last_error')) {
                $table->text('last_error')->nullable();
            }
            if (! Schema::hasColumn('webhook_calls', 'processing_time_ms')) {
                $table->decimal('processing_time_ms', 10, 3)->nullable();
            }
            if (! Schema::hasColumn('webhook_calls', 'owner_type')) {
                $table->string('owner_type')->nullable();
            }
            if (! Schema::hasColumn('webhook_calls', 'owner_id')) {
                $table->string('owner_id')->nullable();
            }
            if (! Schema::hasColumn('webhook_calls', 'retry_count')) {
                $table->integer('retry_count')->default(0);
            }
            if (! Schema::hasColumn('webhook_calls', 'last_retry_at')) {
                $table->timestamp('last_retry_at')->nullable();
            }
        });
    }
}
