<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\EntryPoints\Listeners;

use AIArmada\Cart\Events\CartAbandoned;
use AIArmada\FilamentCart\Actions\NotifyAbandonedCart;
use Illuminate\Contracts\Queue\ShouldQueue;

final class SendCartAbandonedNotification implements ShouldQueue
{
    public function handle(CartAbandoned $event): void
    {
        app(NotifyAbandonedCart::class)->execute($event);
    }
}
