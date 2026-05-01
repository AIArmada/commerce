<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Pages;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentOrders\Resources\OrderResource;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Services\OrderService;
use AIArmada\Orders\States\Processing;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Throwable;
use UnitEnum;

/**
 * Fulfillment queue page for processing orders ready to ship.
 *
 * This page displays orders in "Processing" state and allows warehouse
 * staff to create shipments with tracking numbers.
 */
class FulfillmentQueue extends Page implements HasTable
{
    use InteractsWithTable;

    public bool $isTableVisible = true;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-cube';

    protected string $view = 'filament-shipping::pages.fulfillment-queue';

    protected static string | UnitEnum | null $navigationGroup = 'Shipping';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Fulfillment Queue';

    public static function canAccess(): bool
    {
        if (! class_exists(Order::class)) {
            return false;
        }

        $user = Filament::auth()->user();

        return $user !== null && Gate::forUser($user)->allows('viewAny', Order::class);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return class_exists(Order::class) && static::canAccess() && parent::shouldRegisterNavigation();
    }

    public static function getNavigationBadge(): ?string
    {
        if (! class_exists(Order::class)) {
            return null;
        }

        $includeGlobal = (bool) config('orders.owner.include_global', false);

        if ((bool) config('orders.owner.enabled', true) && OwnerContext::resolve() === null) {
            return null;
        }

        $owner = OwnerContext::resolve();
        $ownerKey = $owner ? ($owner->getMorphClass() . ':' . $owner->getKey()) : 'global';

        $cacheKey = sprintf('filament-shipping.fulfillment-queue.badge.%s.%s', $ownerKey, $includeGlobal ? 'with-global' : 'owner-only');

        $count = Cache::remember($cacheKey, CarbonImmutable::now()->addSeconds(15), function () use ($owner, $includeGlobal): int {
            return Order::query()
                ->forOwner($owner, includeGlobal: $includeGlobal)
                ->whereState('status', Processing::class)
                ->count();
        });

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        if (! class_exists(Order::class)) {
            return null;
        }

        $includeGlobal = (bool) config('orders.owner.include_global', false);

        if ((bool) config('orders.owner.enabled', true) && OwnerContext::resolve() === null) {
            return null;
        }

        $owner = OwnerContext::resolve();
        $ownerKey = $owner ? ($owner->getMorphClass() . ':' . $owner->getKey()) : 'global';

        $cacheKey = sprintf('filament-shipping.fulfillment-queue.badge-color.%s.%s', $ownerKey, $includeGlobal ? 'with-global' : 'owner-only');

        $urgentCount = Cache::remember($cacheKey, CarbonImmutable::now()->addSeconds(15), function () use ($owner, $includeGlobal): int {
            $urgentThreshold = (int) config('filament-shipping.fulfillment.urgent_threshold_hours', 48);

            return Order::query()
                ->forOwner($owner, includeGlobal: $includeGlobal)
                ->whereState('status', Processing::class)
                ->where('created_at', '<=', CarbonImmutable::now()->subHours($urgentThreshold))
                ->count();
        });

        return $urgentCount > 0 ? 'danger' : 'success';
    }

    public function table(Table $table): Table
    {
        $owner = OwnerContext::resolve();
        $includeGlobal = (bool) config('orders.owner.include_global', false);

        return $table
            ->query(
                Order::query()
                    ->forOwner($owner, includeGlobal: $includeGlobal)
                    ->whereState('status', Processing::class)
                    ->with(['customer'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('#')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Order Date')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->description(fn ($record) => $record->created_at->diffForHumans()),

                Tables\Columns\TextColumn::make('customer.full_name')
                    ->label('Customer')
                    ->searchable()
                    ->placeholder('Guest')
                    ->description(function (Order $record): string {
                        $email = $record->customer?->getAttribute('email');

                        return is_string($email) && $email !== '' ? $email : 'Guest';
                    }),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Total')
                    ->money(fn (Order $record): string => $record->currency, divideBy: 100)
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => $state?->color() ?? 'gray')
                    ->icon(fn ($state) => $state?->icon() ?? 'heroicon-o-question-mark-circle'),

                Tables\Columns\TextColumn::make('shipping_method')
                    ->label('Ship Via')
                    ->placeholder('Not set')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('priority')
                    ->label('Priority')
                    ->badge()
                    ->formatStateUsing(function ($record) {
                        $urgentThreshold = (int) config('filament-shipping.fulfillment.urgent_threshold_hours', 48);

                        return $record->created_at->diffInHours(CarbonImmutable::now()) > $urgentThreshold ? 'High' : 'Normal';
                    })
                    ->color(function ($record) {
                        $urgentThreshold = (int) config('filament-shipping.fulfillment.urgent_threshold_hours', 48);

                        return $record->created_at->diffInHours(CarbonImmutable::now()) > $urgentThreshold ? 'danger' : 'success';
                    }),
            ])
            ->defaultSort('created_at', 'asc')
            ->filters([
                Tables\Filters\Filter::make('old_orders')
                    ->label('Older than 24h')
                    ->query(function (Builder $query): Builder {
                        $oldThreshold = (int) config('filament-shipping.fulfillment.old_threshold_hours', 24);

                        return $query->where('created_at', '<=', CarbonImmutable::now()->subHours($oldThreshold));
                    })
                    ->toggle(),

                Tables\Filters\Filter::make('urgent')
                    ->label('Urgent (>48h)')
                    ->query(function (Builder $query): Builder {
                        $urgentThreshold = (int) config('filament-shipping.fulfillment.urgent_threshold_hours', 48);

                        return $query->where('created_at', '<=', CarbonImmutable::now()->subHours($urgentThreshold));
                    })
                    ->toggle(),

                Tables\Filters\SelectFilter::make('shipping_method')
                    ->label('Shipping Method')
                    ->options((array) config('filament-shipping.shipping_methods', [
                        'standard' => 'Standard',
                        'express' => 'Express',
                        'overnight' => 'Overnight',
                    ])),
            ])
            ->actions([
                Action::make('fulfill')
                    ->label('Ship')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->authorize(function (Order $record): bool {
                        $user = Filament::auth()->user();

                        return $user ? Gate::forUser($user)->allows('update', $record) : false;
                    })
                    ->form([
                        Forms\Components\Select::make('carrier')
                            ->label('Carrier')
                            ->options($this->getCarrierOptions())
                            ->required()
                            ->searchable(),

                        Forms\Components\TextInput::make('tracking_number')
                            ->label('Tracking Number')
                            ->required()
                            ->maxLength(100),
                    ])
                    ->action(function (Order $record, array $data): void {
                        try {
                            $service = app(OrderService::class);
                            $service->ship(
                                $record,
                                $data['carrier'],
                                $data['tracking_number'],
                            );

                            Notification::make()
                                ->title('Order marked as shipped')
                                ->success()
                                ->send();
                        } catch (Throwable $exception) {
                            report($exception);

                            Notification::make()
                                ->title('Unable to ship order')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->modalHeading('Ship Order')
                    ->modalDescription(fn ($record) => "Complete shipment for order {$record->order_number}"),

                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => $this->getOrderViewUrl($record))
                    ->openUrlInNewTab(),
            ])
            ->poll(fn (): ?string => $this->isTableVisible ? '30s' : null);
    }

    /**
     * Get carrier options from shipping config.
     *
     * @return array<string, string>
     */
    protected function getCarrierOptions(): array
    {
        $carriers = (array) config('filament-shipping.carriers', []);

        if ($carriers === []) {
            $carriers = (array) config('shipping.drivers', []);
        }

        if ($carriers === []) {
            return [
                'manual' => 'Manual',
                'poslaju' => 'Pos Laju',
                'dhl' => 'DHL',
                'fedex' => 'FedEx',
                'jnt' => 'J&T Express',
            ];
        }

        return collect($carriers)
            ->mapWithKeys(fn ($config, $code) => [$code => $config['name'] ?? ucfirst($code)])
            ->toArray();
    }

    /**
     * Get the URL to view an order.
     */
    protected function getOrderViewUrl(Order $order): string
    {
        if (class_exists(OrderResource::class)) {
            return OrderResource::getUrl('view', ['record' => $order]);
        }

        return '#';
    }
}
