<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Cashier\CashierServiceProvider;

abstract class CashierChipWithStripeTestCase extends CashierChipTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            CashierServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('default_pm_id')->nullable();
            $table->timestamps();
        });

        if (! Schema::hasTable('webhook_calls')) {
            Schema::create('webhook_calls', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('url')->nullable();
                $table->json('headers')->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->text('exception')->nullable();
                $table->timestamps();
            });
        }

        $this->loadMigrationsFrom(__DIR__ . '/../../../vendor/laravel/cashier/database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../../../packages/cashier-chip/database/migrations');
    }
}
