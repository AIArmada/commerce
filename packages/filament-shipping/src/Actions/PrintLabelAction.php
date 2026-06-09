<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\ShippingManager;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\Action as NotificationAction;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Component;
use Throwable;

class PrintLabelAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->name('print_label')
            ->label('Print Label')
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->visible(fn (Shipment $record): bool => $record->tracking_number !== null)
            ->authorize(fn (Shipment $record): bool => auth()->user()?->can('printLabel', $record) ?? false)
            ->action(function (Shipment $record, Component $livewire): mixed {
                try {
                    $shippingManager = app(ShippingManager::class);
                    $driver = $shippingManager->driver($record->carrier_code);
                    $label = $driver->generateLabel($record->tracking_number, [
                        'order_id' => $record->reference,
                    ]);

                    if ($label->hasUrl()) {
                        $url = $label->url;
                        $scheme = parse_url((string) $url, PHP_URL_SCHEME);

                        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array($scheme, ['http', 'https'], true)) {
                            Notification::make()
                                ->title('Invalid Label URL')
                                ->body('The carrier returned an invalid label URL.')
                                ->warning()
                                ->send();

                            return null;
                        }

                        $livewire->js('window.open(' . json_encode((string) $url) . ', \'_blank\')');

                        return null;
                    }

                    if ($label->hasContent()) {
                        $owner = OwnerContext::resolve();
                        $labelToken = (string) Str::ulid();
                        $cacheKey = "shipping_label:{$labelToken}";

                        Cache::put($cacheKey, [
                            'content' => $label->getDecodedContent(),
                            'format' => $label->format,
                            'tracking_number' => $record->tracking_number,
                            'owner_type' => $owner?->getMorphClass(),
                            'owner_id' => $owner?->getKey(),
                            'user_id' => auth()->id(),
                        ], CarbonImmutable::now()->addMinutes(30));

                        $url = URL::temporarySignedRoute('shipping.labels.show', CarbonImmutable::now()->addMinutes(30), [
                            'trackingNumber' => $record->tracking_number,
                            'token' => $labelToken,
                        ]);

                        $livewire->js('window.open(' . json_encode((string) $url) . ', \'_blank\')');

                        return null;
                    }

                    Notification::make()
                        ->title('Label Not Available')
                        ->body('Label URL is not available for this shipment.')
                        ->warning()
                        ->send();

                    return null;
                } catch (Throwable $e) {
                    report($e);

                    Notification::make()
                        ->title('Label Generation Failed')
                        ->body('Unable to generate label. Please try again or check logs.')
                        ->danger()
                        ->send();

                    return null;
                }
            });
    }

    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'print_label');
    }

    public static function bulkAction(?string $name = null): BulkAction
    {
        return BulkAction::make($name ?? 'bulk_print_labels')
            ->label('Print Labels')
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->deselectRecordsAfterCompletion()
            ->authorize(fn (): bool => auth()->user() !== null)
            ->requiresConfirmation()
            ->modalHeading('Print Shipping Labels')
            ->modalDescription('Generate and print labels for selected shipments.')
            ->modalSubmitActionLabel('Print All')
            ->action(function (Collection $records, Component $livewire): void {
                $user = auth()->user();

                if ($user === null) {
                    Notification::make()
                        ->title('Authentication Required')
                        ->body('Please sign in to print labels.')
                        ->danger()
                        ->send();

                    return;
                }

                $shippingManager = app(ShippingManager::class);
                $owner = OwnerContext::resolve();
                $labels = [];
                $errors = [];

                foreach ($records as $record) {
                    if (! $record instanceof Shipment || $record->tracking_number === null) {
                        continue;
                    }

                    if (! $user->can('printLabel', $record)) {
                        continue;
                    }

                    try {
                        $driver = $shippingManager->driver($record->carrier_code);
                        $label = $driver->generateLabel($record->tracking_number, [
                            'order_id' => $record->reference,
                        ]);

                        if ($label->hasUrl()) {
                            $url = $label->url;
                            $scheme = parse_url((string) $url, PHP_URL_SCHEME);

                            if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array($scheme, ['http', 'https'], true)) {
                                $errors[] = "{$record->tracking_number}: invalid label URL";

                                continue;
                            }

                            $labels[] = [
                                'tracking' => $record->tracking_number,
                                'carrier' => $record->carrier_code,
                                'url' => $url,
                                'type' => 'url',
                            ];

                            continue;
                        }

                        if ($label->hasContent()) {
                            $labelToken = (string) Str::ulid();
                            $cacheKey = "shipping_label:{$labelToken}";

                            Cache::put($cacheKey, [
                                'content' => $label->getDecodedContent(),
                                'format' => $label->format,
                                'tracking_number' => $record->tracking_number,
                                'owner_type' => $owner?->getMorphClass(),
                                'owner_id' => $owner?->getKey(),
                                'user_id' => $user->getAuthIdentifier(),
                            ], CarbonImmutable::now()->addMinutes(30));

                            $url = URL::temporarySignedRoute('shipping.labels.show', CarbonImmutable::now()->addMinutes(30), [
                                'trackingNumber' => $record->tracking_number,
                                'token' => $labelToken,
                            ]);

                            $labels[] = [
                                'tracking' => $record->tracking_number,
                                'carrier' => $record->carrier_code,
                                'url' => $url,
                                'type' => 'cached',
                            ];

                            continue;
                        }

                        $errors[] = "{$record->tracking_number}: no label content";
                    } catch (Throwable $e) {
                        report($e);
                        $errors[] = "{$record->tracking_number}: " . $e->getMessage();
                    }
                }

                if (count($labels) === 0 && count($errors) === 0) {
                    Notification::make()
                        ->title('No Printable Labels')
                        ->body('None of the selected shipments have printable labels.')
                        ->warning()
                        ->send();

                    return;
                }

                if (count($labels) === 1) {
                    $livewire->js('window.open(' . json_encode((string) $labels[0]['url']) . ', \'_blank\')');

                    Notification::make()
                        ->title('Label Ready')
                        ->body("Opening label for {$labels[0]['tracking']} in new tab.")
                        ->success()
                        ->send();

                    return;
                }

                if (count($labels) > 0) {
                    Notification::make()
                        ->title('Labels Generated')
                        ->body(count($labels) . ' label(s) ready. Click each to open.')
                        ->success()
                        ->send();

                    foreach ($labels as $label) {
                        $carrierName = ucfirst($label['carrier']);

                        Notification::make()
                            ->title("{$carrierName}: {$label['tracking']}")
                            ->body('Click to open shipping label')
                            ->actions([
                                NotificationAction::make('open')
                                    ->label('Open Label')
                                    ->url($label['url'], true),
                            ])
                            ->persistent()
                            ->send();
                    }
                }

                if (count($errors) > 0) {
                    Notification::make()
                        ->title('Some Labels Failed')
                        ->body(implode("\n", array_slice($errors, 0, 5)))
                        ->warning()
                        ->send();
                }
            });
    }
}
