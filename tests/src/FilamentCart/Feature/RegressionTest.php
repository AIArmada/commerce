<?php

declare(strict_types=1);

use AIArmada\Cart\Events\CartAbandoned;
use AIArmada\Cart\Models\Condition;
use AIArmada\Cart\Snapshots\CartSnapshot as Cart;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\Pending;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Contracts\OwnerScopedJob;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerJobContext;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\FilamentCart\Actions\ApplyConditionAction;
use AIArmada\FilamentCart\Actions\RemoveConditionAction;
use AIArmada\FilamentCart\Jobs\RemoveConditionFromAllCartsJob;
use AIArmada\FilamentCart\Listeners\SendCartAbandonedNotification;
use AIArmada\FilamentCart\Notifications\CartAbandonedNotification;
use AIArmada\FilamentCart\Pages\CartDashboard;
use AIArmada\FilamentCart\Pages\LiveDashboardPage;
use AIArmada\FilamentCart\Resources\CartItemResource;
use AIArmada\FilamentCart\Resources\CartResource;
use AIArmada\FilamentCart\Resources\CartResource\RelationManagers\ConditionsRelationManager;
use AIArmada\FilamentCart\Resources\CartResource\Tables\CartsTable;
use AIArmada\FilamentCart\Resources\ConditionResource;
use AIArmada\FilamentCart\Services\CartDownloadService;
use AIArmada\FilamentCart\Support\ConditionTargetLabels;
use AIArmada\FilamentCart\Widgets\CartStatsWidget;
use AIArmada\FilamentCart\Widgets\RecentActivityWidget;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\RoutesNotifications;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

class AnonymousRepairNotifiable
{
    use RoutesNotifications;
}

beforeEach(function (): void {
    Cache::flush();
});

describe('snapshot conditions relation manager', function (): void {
    it('renders only snapshot-condition columns without stored-condition bulk delete', function (): void {
        $table = (new ConditionsRelationManager)->table(Table::make(Mockery::mock(HasTable::class)));

        $columns = array_keys($table->getColumns());

        expect($columns)->toContain('name', 'type', 'target', 'value', 'operator', 'parsed_value', 'order')
            ->not->toContain('display_name')
            ->not->toContain('is_active');

        $recordActions = collect($table->getRecordActions());
        expect($recordActions->map(fn ($action): string => $action->getName())->all())
            ->toBe(['removeCondition']);

        $toolbarNames = collect($table->getToolbarActions())
            ->flatMap(function ($action): array {
                if ($action instanceof BulkActionGroup) {
                    return array_map(fn ($child): string => $child->getName(), $action->getActions());
                }

                return [$action->getName()];
            })
            ->all();

        expect($toolbarNames)->toContain('removeSelected')
            ->not->toContain('deleteSelected');
    });
});

describe('retry URL validation', function (): void {
    it('rejects non-http(s) retry URLs', function (): void {
        $listener = new SendCartAbandonedNotification;
        $method = new ReflectionMethod($listener, 'resolveRetryUrl');

        config(['app.url' => 'https://shop.example.com']);

        $javascript = (new CheckoutSession)->forceFill(['payment_redirect_url' => 'javascript:alert(1)']);
        $relative = (new CheckoutSession)->forceFill(['payment_redirect_url' => '/evil']);
        $valid = (new CheckoutSession)->forceFill(['payment_redirect_url' => 'https://pay.example.com/retry/1']);

        expect($method->invoke($listener, $javascript))->toBe('https://shop.example.com');
        expect($method->invoke($listener, $relative))->toBe('https://shop.example.com');
        expect($method->invoke($listener, $valid))->toBe('https://pay.example.com/retry/1');
    });

    it('enforces the allowed retry hosts allowlist when configured', function (): void {
        $listener = new SendCartAbandonedNotification;
        $method = new ReflectionMethod($listener, 'resolveRetryUrl');

        config(['app.url' => 'https://shop.example.com']);
        config(['filament-cart.notifications.abandoned_cart.allowed_retry_hosts' => ['pay.example.com']]);

        $allowed = (new CheckoutSession)->forceFill(['payment_redirect_url' => 'https://pay.example.com/retry/1']);
        $denied = (new CheckoutSession)->forceFill(['payment_redirect_url' => 'https://evil.example.com/steal']);

        expect($method->invoke($listener, $allowed))->toBe('https://pay.example.com/retry/1');
        expect($method->invoke($listener, $denied))->toBe('https://shop.example.com');
    });

    it('strips line breaks from the notification subject', function (): void {
        $notification = new CartAbandonedNotification([
            'offer_name' => "VIP\r\nBcc: evil@example.com",
            'retry_url' => 'https://shop.example.com',
            'formatted_total' => 'MYR 10.00',
        ]);

        $mail = $notification->toMail(new AnonymousRepairNotifiable);

        expect($mail->subject)->not->toContain("\r", "\n");
    });
});

describe('purchaser email validation', function (): void {
    it('skips notification for malformed purchaser emails', function (): void {
        Notification::fake();

        config()->set('cart.owner.enabled', true);
        config()->set('cart.owner.include_global', false);
        config()->set('cart.owner.auto_assign_on_create', true);
        config()->set('checkout.owner.enabled', true);
        config()->set('checkout.owner.include_global', false);
        config()->set('checkout.owner.auto_assign_on_create', true);

        $owner = User::factory()->create();

        $event = OwnerContext::withOwner($owner, function (): CartAbandoned {
            $cart = Cart::factory()->create([
                'identifier' => 'abandoned-bad-email',
                'instance' => 'default',
                'items' => [['id' => 'offer-1', 'name' => 'Offer', 'price' => 1000, 'quantity' => 1]],
                'items_count' => 1,
                'quantity' => 1,
                'subtotal' => 1000,
                'total' => 1000,
                'currency' => 'MYR',
                'checkout_started_at' => now()->subHour(),
            ]);

            CheckoutSession::create([
                'cart_id' => $cart->id,
                'billing_data' => ['email' => 'not-an-email'],
                'payment_data' => [],
                'status' => Pending::class,
            ]);

            return CartAbandoned::fromCart($cart);
        });

        OwnerContext::withOwner(null, function () use ($event): void {
            app(SendCartAbandonedNotification::class)->handle($event);
        });

        Notification::assertNothingSent();
    });

    it('runs the abandonment listener on the queue', function (): void {
        expect(new SendCartAbandonedNotification)->toBeInstanceOf(ShouldQueue::class);
    });
});

describe('badge caching', function (): void {
    it('serves repeated dashboard badge reads from cache', function (): void {
        Cart::query()->create([
            'identifier' => 'badge-cart',
            'instance' => 'default',
            'currency' => 'USD',
            'checkout_abandoned_at' => now()->subHour(),
        ]);

        CartDashboard::getNavigationBadge();
        CartDashboard::getNavigationBadgeColor();

        DB::enableQueryLog();
        DB::flushQueryLog();

        CartDashboard::getNavigationBadge();
        CartDashboard::getNavigationBadgeColor();

        expect(DB::getQueryLog())->toBe([]);

        DB::disableQueryLog();
    });

    it('hides the items badge at zero and caches resource badges', function (): void {
        expect(CartItemResource::getNavigationBadge())->toBeNull();

        Cart::query()->create([
            'identifier' => 'badge-cart-2',
            'instance' => 'default',
            'currency' => 'USD',
        ]);

        Cache::flush();

        CartResource::getNavigationBadge();
        ConditionResource::getNavigationBadge();

        DB::enableQueryLog();
        DB::flushQueryLog();

        CartResource::getNavigationBadge();
        ConditionResource::getNavigationBadge();

        expect(DB::getQueryLog())->toBe([]);

        DB::disableQueryLog();
    });
});

describe('bulk operation resilience', function (): void {
    it('reports per-row failures instead of aborting the selection', function (): void {
        config()->set('cart.owner.enabled', true);
        config()->set('cart.owner.include_global', false);
        config()->set('cart.owner.auto_assign_on_create', true);

        $ownerA = User::query()->create(['name' => 'A', 'email' => 'bulk-a@example.com', 'password' => 'secret']);
        $ownerB = User::query()->create(['name' => 'B', 'email' => 'bulk-b@example.com', 'password' => 'secret']);

        app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new FixedOwnerResolver($ownerA));

        $foreign = OwnerContext::withOwner($ownerB, fn () => Cart::query()->create([
            'identifier' => 'foreign-cart',
            'instance' => 'default',
            'currency' => 'USD',
        ]));

        $method = new ReflectionMethod(CartsTable::class, 'runBulkOperation');

        $method->invoke(null, new Collection([$foreign]), 'clear');

        $notifications = session('filament.notifications', []);

        expect($notifications)->not->toBe([]);
        expect((string) json_encode($notifications))->toContain('failed');
    });
});

describe('item query eager loading', function (): void {
    it('eager loads the parent cart for items', function (): void {
        $eagerLoads = CartItemResource::getEloquentQuery()->getEagerLoads();

        expect($eagerLoads)->toHaveKey('cart');
    });
});

describe('multi-currency total value', function (): void {
    it('shows the dominant currency with a breakdown instead of mixing sums', function (): void {
        config()->set('cart.money.default_currency', 'USD');

        Cart::query()->create([
            'identifier' => 'usd-cart',
            'instance' => 'default',
            'currency' => 'USD',
            'items_count' => 1,
            'quantity' => 1,
            'subtotal' => 2500,
            'total' => 2500,
        ]);
        Cart::query()->create([
            'identifier' => 'myr-cart',
            'instance' => 'default',
            'currency' => 'MYR',
            'items_count' => 1,
            'quantity' => 1,
            'subtotal' => 9900,
            'total' => 9900,
        ]);

        $widget = new CartStatsWidget;
        $method = (new ReflectionClass($widget))->getMethod('getStats');

        /** @var array<int, Stat> $stats */
        $stats = $method->invoke($widget);

        expect($stats[3]->getValue())->toContain('(+1)');
        expect($stats[3]->getDescription())->toContain('·');
    });
});

describe('scope key', function (): void {
    it('derives the global scope key when owner scoping is disabled', function (): void {
        config()->set('cart.owner.enabled', false);

        $method = new ReflectionMethod(
            'AIArmada\\FilamentCart\\Resources\\CartResource\\Schemas\\CartForm',
            'currentOwnerScopeKey'
        );

        expect($method->invoke(null))->toBe('global');
    });
});

describe('activity widget query', function (): void {
    it('paginates without a row cap and keeps owner attributes', function (): void {
        $widget = new RecentActivityWidget;
        $method = (new ReflectionClass($widget))->getMethod('getActivityQuery');

        /** @var Builder $query */
        $query = $method->invoke($widget);

        expect($query->getQuery()->limit)->toBeNull();
        expect($query->toSql())->toContain('owner_type')
            ->toContain('owner_id');
    });
});

describe('cart resolution failures', function (): void {
    it('throws a catchable exception for missing cart records', function (): void {
        $apply = new ReflectionMethod(ApplyConditionAction::class, 'resolveCartRecord');
        $remove = new ReflectionMethod(RemoveConditionAction::class, 'resolveCartRecord');

        expect(fn () => $apply->invoke(null, null))->toThrow(InvalidArgumentException::class);
        expect(fn () => $remove->invoke(null, null))->toThrow(InvalidArgumentException::class);
    });
});

describe('bounded condition search', function (): void {
    it('caps search results at one page', function (): void {
        config()->set('cart.owner.enabled', false);

        for ($i = 0; $i < 60; $i++) {
            Condition::factory()->create([
                'name' => "bulk-searchable-{$i}",
                'display_name' => "Bulk Searchable {$i}",
                'target' => 'cart@cart_subtotal/aggregate',
                'is_active' => true,
            ]);
        }

        $method = new ReflectionMethod(ApplyConditionAction::class, 'searchConditionOptions');

        /** @var array<string, string> $results */
        $results = $method->invoke(null, 'bulk-searchable', false);

        expect($results)->toHaveCount(50);
    });

    it('resolves labels only within the owner scope', function (): void {
        config()->set('cart.owner.enabled', true);
        config()->set('cart.owner.include_global', false);

        $owner = User::query()->create(['name' => 'C', 'email' => 'label-c@example.com', 'password' => 'secret']);

        app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new FixedOwnerResolver($owner));

        $condition = Condition::factory()->create([
            'name' => 'label-scope-check',
            'display_name' => 'Label Scope Check',
            'target' => 'cart@cart_subtotal/aggregate',
            'is_active' => true,
        ]);
        $condition->assignOwner($owner)->save();

        $method = new ReflectionMethod(ApplyConditionAction::class, 'resolveConditionLabel');

        expect($method->invoke(null, $condition->id))->toBe('Label Scope Check');
        expect($method->invoke(null, 'missing-id'))->toBeNull();
    });
});

describe('condition target labels', function (): void {
    it('maps stored DSL targets to display labels', function (): void {
        expect(ConditionTargetLabels::label('cart@cart_subtotal/aggregate'))->toBe('Cart Subtotal');
        expect(ConditionTargetLabels::label('cart@grand_total/aggregate'))->toBe('Cart Total');
        expect(ConditionTargetLabels::label('items@item_discount/per-item'))->toBe('Individual Items');
        expect(ConditionTargetLabels::label('cart@custom_phase/custom'))->toBe('Cart Custom Phase Custom');
        expect(ConditionTargetLabels::label(null))->toBe('—');
    });
});

describe('download service', function (): void {
    it('throws on JSON encoding failure instead of exporting an empty payload', function (): void {
        $cart = new Cart([
            'identifier' => "\xB1\x31 invalid utf-8",
            'instance' => 'default',
        ]);

        expect(fn () => app(CartDownloadService::class)->download($cart))->toThrow(JsonException::class);
    });

    it('refuses cross-owner exports', function (): void {
        config()->set('cart.owner.enabled', true);
        config()->set('cart.owner.include_global', false);
        config()->set('cart.owner.auto_assign_on_create', true);

        $ownerA = User::query()->create(['name' => 'D', 'email' => 'dl-a@example.com', 'password' => 'secret']);
        $ownerB = User::query()->create(['name' => 'E', 'email' => 'dl-b@example.com', 'password' => 'secret']);

        app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new FixedOwnerResolver($ownerA));

        $foreign = OwnerContext::withOwner($ownerB, fn () => Cart::query()->create([
            'identifier' => 'foreign-export',
            'instance' => 'default',
            'currency' => 'USD',
        ]));

        expect(fn () => app(CartDownloadService::class)->download($foreign))->toThrow(AuthorizationException::class);
    });
});

describe('queued condition removal', function (): void {
    it('carries an explicit owner context payload', function (): void {
        config()->set('cart.owner.enabled', true);

        $owner = User::query()->create(['name' => 'F', 'email' => 'job-f@example.com', 'password' => 'secret']);

        $owned = OwnerContext::withOwner($owner, fn () => Condition::factory()->create([
            'name' => 'owned-job-condition',
            'target' => 'cart@cart_subtotal/aggregate',
        ]));
        $owned->refresh();

        $job = RemoveConditionFromAllCartsJob::forCondition($owned);

        expect($job)->toBeInstanceOf(ShouldQueue::class)
            ->toBeInstanceOf(OwnerScopedJob::class);
        expect($job->ownerContext()->ownerType)->toBe($owner::class);
        expect((string) $job->ownerContext()->ownerId)->toBe((string) $owner->getKey());
        expect($job->ownerContext()->isExplicitGlobal())->toBeFalse();

        $global = OwnerContext::withOwner(null, fn () => Condition::factory()->create([
            'name' => 'global-job-condition',
            'target' => 'cart@cart_subtotal/aggregate',
            'owner_type' => null,
            'owner_id' => null,
        ]));

        expect(RemoveConditionFromAllCartsJob::forCondition($global)->ownerContext()->isExplicitGlobal())->toBeTrue();
    });

    it('skips gracefully when the condition no longer exists', function (): void {
        $context = OwnerJobContext::explicitGlobal();
        $job = new RemoveConditionFromAllCartsJob('missing-condition-id', $context);

        $job->handle();

        expect(true)->toBeTrue();
    });
});

describe('dashboard access gate', function (): void {
    it('denies access when monitoring is disabled', function (): void {
        config()->set('filament-cart.features.monitoring', false);

        expect(LiveDashboardPage::canAccess())->toBeFalse();
    });

    it('allows panel users by default and enforces the permission when configured', function (): void {
        config()->set('filament-cart.features.monitoring', true);
        config()->set('filament-cart.features.monitoring_permission', null);

        expect(LiveDashboardPage::canAccess())->toBeTrue();
        expect(CartDashboard::canAccess())->toBeTrue();

        config()->set('filament-cart.features.monitoring_permission', 'view-cart-monitoring');

        expect(LiveDashboardPage::canAccess())->toBeFalse();

        $user = User::factory()->create();
        Gate::define('view-cart-monitoring', fn ($candidate): bool => $candidate->is($user));

        $this->actingAs($user);

        expect(LiveDashboardPage::canAccess())->toBeTrue();
        expect(CartDashboard::canAccess())->toBeTrue();
    });
});

describe('AUD B7 single-query activity chart', function (): void {
    it('buckets seven days of activity with one grouped query', function (): void {
        $today = Cart::query()->create([
            'identifier' => 'chart-today',
            'instance' => 'default',
            'currency' => 'USD',
            'items_count' => 1,
        ]);
        Cart::query()->whereKey($today->id)->update(['updated_at' => now()]);

        $old = Cart::query()->create([
            'identifier' => 'chart-old',
            'instance' => 'default',
            'currency' => 'USD',
            'items_count' => 1,
        ]);
        Cart::query()->whereKey($old->id)->update(['updated_at' => now()->subDays(3)]);

        $widget = new CartStatsWidget;
        $method = (new ReflectionClass($widget))->getMethod('getActiveCartsChart');

        DB::enableQueryLog();
        DB::flushQueryLog();

        /** @var array<int, int> $chart */
        $chart = $method->invoke($widget);

        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        expect($chart)->toHaveCount(7);
        expect($chart[6])->toBe(1);
        expect($chart[3])->toBe(1);
        expect(array_sum($chart))->toBe(2);
        expect($queries)->toHaveCount(1);
    });
});
