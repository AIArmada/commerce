<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders\EntryPoints\Http\Controllers;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Runtime\OrderInvoiceDownload;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Illuminate\Contracts\Support\Responsable;
use Symfony\Component\HttpFoundation\Response;

final readonly class DownloadOrderInvoiceController
{
    public function __invoke(string $order): Responsable | Response
    {
        if ((bool) config('orders.owner.enabled', false) && OwnerContext::resolve() === null && ! OwnerContext::isExplicitGlobal()) {
            abort(404);
        }

        $record = Order::query()
            ->forOwner(includeGlobal: (bool) config('orders.owner.include_global', false))
            ->findOrFail($order);

        $user = Filament::auth()->user();

        abort_unless($user && Gate::forUser($user)->allows('view', $record), 403);

        return app(OrderInvoiceDownload::class)->download((string) $record->getKey());
    }
}
