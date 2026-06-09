<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Resources\UnifiedSubscriptionResource\Tables;

use AIArmada\Cashier\Support\GatewayDetector;
use AIArmada\Cashier\Support\SubscriptionStatus;
use AIArmada\FilamentCashier\Policies\SubscriptionPolicy;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class SubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        $gatewayDetector = app(GatewayDetector::class);

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('userId')
                    ->label(__('filament-cashier::subscriptions.table.user'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('gateway')
                    ->label(__('filament-cashier::subscriptions.table.gateway'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $gatewayDetector->getLabel($state))
                    ->color(fn (string $state): string => $gatewayDetector->getColor($state))
                    ->icon(fn (string $state): string => $gatewayDetector->getIcon($state)),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('filament-cashier::subscriptions.table.type'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('planId')
                    ->label(__('filament-cashier::subscriptions.table.plan'))
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('filament-cashier::subscriptions.table.status'))
                    ->badge()
                    ->formatStateUsing(fn (SubscriptionStatus $state): string => $state->label())
                    ->color(fn (SubscriptionStatus $state): string => $state->color())
                    ->icon(fn (SubscriptionStatus $state): string => $state->icon()),

                Tables\Columns\TextColumn::make('formattedAmount')
                    ->label(__('filament-cashier::subscriptions.table.amount'))
                    ->getStateUsing(fn (Model $record): string => (string) $record->getAttribute('formatted_amount')),

                Tables\Columns\TextColumn::make('quantity')
                    ->label(__('filament-cashier::subscriptions.table.quantity'))
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('trialEndsAt')
                    ->label(__('filament-cashier::subscriptions.table.trial_ends_at'))
                    ->date(config('filament-cashier.tables.date_format', 'M d, Y'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('nextBillingDate')
                    ->label(__('filament-cashier::subscriptions.table.next_billing'))
                    ->date(config('filament-cashier.tables.date_format', 'M d, Y'))
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('createdAt')
                    ->label(__('filament-cashier::subscriptions.table.created_at'))
                    ->date(config('filament-cashier.tables.date_format', 'M d, Y'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('gateway')
                    ->label(__('filament-cashier::subscriptions.filters.gateway'))
                    ->options($gatewayDetector->getGatewayOptions())
                    ->query(fn (Builder $query, array $data): Builder => $query),

                Tables\Filters\SelectFilter::make('status')
                    ->label(__('filament-cashier::subscriptions.filters.status'))
                    ->options(
                        collect(SubscriptionStatus::cases())
                            ->mapWithKeys(fn (SubscriptionStatus $status) => [$status->value => $status->label()])
                            ->toArray()
                    )
                    ->query(fn (Builder $query, array $data): Builder => $query),
            ])
            ->actions([
                ViewAction::make(),

                Action::make('cancel')
                    ->label(__('filament-cashier::subscriptions.actions.cancel'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Model $record): bool => $record->getAttribute('status')->isCancelable())
                    ->requiresConfirmation()
                    ->modalHeading(fn (Model $record): string => __('filament-cashier::subscriptions.actions.cancel_heading', [
                        'gateway' => $record->getAttribute('gateway_config')['label'],
                    ]))
                    ->modalDescription(__('filament-cashier::subscriptions.actions.cancel_description'))
                    ->action(function (Model $record): void {
                        $user = auth()->user();

                        if ($user === null || ! app(SubscriptionPolicy::class)->cancel($user, $record->getAttribute('original'))) {
                            throw new AuthorizationException('Not authorized to cancel this subscription.');
                        }

                        $original = $record->getAttribute('original');

                        if (method_exists($original, 'cancel')) {
                            $original->cancel();
                        }
                    })
                    ->successNotificationTitle(__('filament-cashier::subscriptions.actions.cancel_success')),

                Action::make('resume')
                    ->label(__('filament-cashier::subscriptions.actions.resume'))
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn (Model $record): bool => $record->getAttribute('status')->isResumable())
                    ->requiresConfirmation()
                    ->action(function (Model $record): void {
                        $user = auth()->user();

                        if ($user === null || ! app(SubscriptionPolicy::class)->resume($user, $record->getAttribute('original'))) {
                            throw new AuthorizationException('Not authorized to resume this subscription.');
                        }

                        $original = $record->getAttribute('original');

                        if (method_exists($original, 'resume')) {
                            $original->resume();
                        }
                    })
                    ->successNotificationTitle(__('filament-cashier::subscriptions.actions.resume_success')),

                Action::make('view_external')
                    ->label(fn (Model $record): string => __('filament-cashier::subscriptions.actions.view_external', [
                        'gateway' => $record->getAttribute('gateway_config')['label'],
                    ]))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Model $record): string => (string) $record->getAttribute('external_dashboard_url'))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('cancel')
                        ->label(__('filament-cashier::subscriptions.bulk.cancel'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $user = auth()->user();

                            if ($user === null) {
                                throw new AuthorizationException('Not authorized to cancel subscriptions.');
                            }

                            $policy = app(SubscriptionPolicy::class);

                            $records->each(function (Model $record) use ($user, $policy): void {
                                $original = $record->getAttribute('original');

                                if (! $policy->cancel($user, $original)) {
                                    throw new AuthorizationException('Not authorized to cancel this subscription.');
                                }

                                if (method_exists($original, 'cancel')) {
                                    $original->cancel();
                                }
                            });
                        }),
                ]),
            ])
            ->defaultSort('createdAt', 'desc')
            ->poll(config('filament-cashier.tables.polling_interval', '45s'))
            ->emptyStateHeading(__('filament-cashier::subscriptions.empty.title'))
            ->emptyStateDescription(__('filament-cashier::subscriptions.empty.description'))
            ->emptyStateIcon('heroicon-o-credit-card');
    }
}
