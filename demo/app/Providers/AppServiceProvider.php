<?php

declare(strict_types=1);

namespace App\Providers;

use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;
use App\Listeners\HandleChipPaymentSuccess;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        config()->set('commerce-support.owner.team_type', User::class);

        $this->app->bind(OwnerResolverInterface::class, function (): OwnerResolverInterface {
            return new class implements OwnerResolverInterface
            {
                public function resolve(): ?Model
                {
                    $user = Auth::user();

                    return $user instanceof Model ? $user : null;
                }
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'order' => Order::class,
            'product' => Product::class,
            'user' => User::class,
            'permission' => Permission::class,
            'role' => Role::class,
        ]);

        // Register CHIP webhook listeners for order processing
        Event::listen(PurchasePaid::class, HandleChipPaymentSuccess::class);

        FilamentTimezone::set('Asia/Kuala_Lumpur');

    }
}
