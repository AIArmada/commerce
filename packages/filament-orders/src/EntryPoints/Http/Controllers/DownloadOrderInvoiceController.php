<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders\EntryPoints\Http\Controllers;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Actions\GenerateInvoice;
use AIArmada\Orders\Models\Order;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPdf\PdfBuilder;
use Symfony\Component\HttpFoundation\Response;

final readonly class DownloadOrderInvoiceController
{
    public function __invoke(string $order): PdfBuilder | Response
    {
        if ((bool) config('orders.owner.enabled', false) && OwnerContext::resolve() === null && ! OwnerContext::isExplicitGlobal()) {
            abort(404);
        }

        $record = Order::query()
            ->forOwner(includeGlobal: (bool) config('orders.owner.include_global', false))
            ->findOrFail($order);

        $user = Filament::auth()->user();

        abort_unless($user && Gate::forUser($user)->allows('view', $record), 403);

        return app(GenerateInvoice::class)->download($record);
    }
}
