<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Cashier;

use AIArmada\Cashier\Cashier;
use AIArmada\Cashier\CashierServiceProvider;
use AIArmada\Cashier\GatewayManager;
use AIArmada\Cashier\Models\Subscription;
use AIArmada\Cashier\Models\SubscriptionItem;
use AIArmada\Commerce\Tests\Cashier\Fixtures\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class CashierTestCase extends Orchestra
{
    protected ?GatewayManager $gatewayManager = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();

        Cashier::useCustomerModel(User::class);
        Cashier::useSubscriptionModel(Subscription::class);
        Cashier::useSubscriptionItemModel(SubscriptionItem::class);

        if ($this->app->bound(GatewayManager::class)) {
            $this->gatewayManager = $this->app->make(GatewayManager::class);
        }
    }

    protected function getPackageProviders($app): array
    {
        return [
            \Illuminate\Events\EventServiceProvider::class,
            \Illuminate\Session\SessionServiceProvider::class,
            \Illuminate\Cache\CacheServiceProvider::class,
            \Illuminate\Database\DatabaseServiceProvider::class,
            CashierServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.env', 'testing');
        $app['config']->set('database.default', 'testing');

        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Configure session
        $app['config']->set('session.driver', 'array');

        // Configure cache
        $app['config']->set('cache.default', 'array');
        $app['config']->set('cache.stores.array', [
            'driver' => 'array',
            'serialize' => false,
        ]);

        // Configure Cashier settings for testing
        $app['config']->set('cashier.default', 'stripe');
        $app['config']->set('cashier.currency', 'USD');
        $app['config']->set('cashier.locale', 'en_US');

        // Configure gateway settings
        $app['config']->set('cashier.gateways', [
            'stripe' => [
                'driver' => 'stripe',
                'key' => 'pk_test_xxx',
                'secret' => 'sk_test_xxx',
                'webhook_secret' => 'whsec_xxx',
                'currency' => 'USD',
                'currency_locale' => 'en_US',
            ],
            'chip' => [
                'driver' => 'chip',
                'brand_id' => 'test_brand_id',
                'api_key' => 'test_api_key',
                'webhook_key' => 'test_webhook_key',
                'currency' => 'MYR',
                'currency_locale' => 'ms_MY',
            ],
        ]);

        // Configure models
        $app['config']->set('cashier.models', [
            'customer' => User::class,
            'subscription' => Subscription::class,
            'subscription_item' => SubscriptionItem::class,
        ]);
    }

    protected function setUpDatabase(): void
    {
        // Users table
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('stripe_id')->nullable()->index();
            $table->string('chip_id')->nullable()->index();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
        });

        // Gateway subscriptions table
        Schema::create('gateway_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->morphs('billable');
            $table->string('gateway')->default('stripe')->index();
            $table->string('gateway_id')->index();
            $table->string('gateway_status')->nullable();
            $table->string('gateway_price')->nullable();
            $table->string('type')->index();
            $table->integer('quantity')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('next_billing_at')->nullable();
            $table->string('billing_interval')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->unique(['billable_type', 'billable_id', 'type', 'gateway']);
        });

        // Gateway subscription items table
        Schema::create('gateway_subscription_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')->constrained('gateway_subscriptions')->cascadeOnDelete();
            $table->string('gateway_id')->index();
            $table->string('gateway_product')->nullable();
            $table->string('gateway_price')->index();
            $table->integer('quantity')->nullable();
            $table->integer('unit_amount')->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'gateway_price']);
        });
    }

    protected function createUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ], $attributes));
    }

    protected function createSubscription(User $user, array $attributes = []): Subscription
    {
        return Subscription::create(array_merge([
            'billable_type' => User::class,
            'billable_id' => $user->id,
            'type' => 'default',
            'gateway' => 'stripe',
            'gateway_id' => 'sub_'.uniqid(),
            'gateway_status' => Subscription::STATUS_ACTIVE,
            'gateway_price' => 'price_xxx',
            'quantity' => 1,
        ], $attributes));
    }

    protected function createSubscriptionItem(Subscription $subscription, array $attributes = []): SubscriptionItem
    {
        return SubscriptionItem::create(array_merge([
            'subscription_id' => $subscription->id,
            'gateway_id' => 'si_'.uniqid(),
            'gateway_product' => 'prod_xxx',
            'gateway_price' => 'price_xxx',
            'quantity' => 1,
            'unit_amount' => 1000,
        ], $attributes));
    }
}
