<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\CampaignResource\Tables;

use AIArmada\Vouchers\Campaigns\Enums\CampaignObjective;
use AIArmada\Vouchers\Campaigns\Enums\CampaignStatus;
use AIArmada\Vouchers\Campaigns\Enums\CampaignType;
use AIArmada\Vouchers\Campaigns\Models\Campaign;
use Akaunting\Money\Money;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class CampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['variants', 'vouchers']))
            ->columns([
                TextColumn::make('name')
                    ->label('Campaign')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon(Heroicon::Megaphone),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(static fn (CampaignType $state): string => match ($state) {
                        CampaignType::Promotional => 'primary',
                        CampaignType::Acquisition => 'success',
                        CampaignType::Retention => 'info',
                        CampaignType::Loyalty => 'warning',
                        CampaignType::Seasonal => 'danger',
                        CampaignType::Flash => 'primary',
                        CampaignType::Referral => 'success',
                    })
                    ->formatStateUsing(static fn (CampaignType $state): string => $state->label())
                    ->sortable(),

                TextColumn::make('objective')
                    ->label('Objective')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(static fn (CampaignObjective $state): string => $state->label())
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(static fn (CampaignStatus $state): string => match ($state) {
                        CampaignStatus::Draft => 'gray',
                        CampaignStatus::Scheduled => 'info',
                        CampaignStatus::Active => 'success',
                        CampaignStatus::Paused => 'warning',
                        CampaignStatus::Completed => 'primary',
                        CampaignStatus::Cancelled => 'danger',
                    })
                    ->formatStateUsing(static fn (CampaignStatus $state): string => $state->label())
                    ->sortable(),

                TextColumn::make('budget_display')
                    ->label('Budget')
                    ->state(static function (Campaign $record): string {
                        if ($record->budget_cents === null) {
                            return 'Unlimited';
                        }

                        $currency = mb_strtoupper((string) config('filament-vouchers.default_currency', 'MYR'));
                        $spent = (string) Money::{$currency}($record->spent_cents);
                        $budget = (string) Money::{$currency}($record->budget_cents);

                        return "{$spent} / {$budget}";
                    })
                    ->description(static function (Campaign $record): ?string {
                        if ($record->budget_cents === null || $record->budget_cents === 0) {
                            return null;
                        }

                        $percentage = ($record->spent_cents / $record->budget_cents) * 100;

                        return number_format($percentage, 1) . '% used';
                    }),

                TextColumn::make('redemptions_display')
                    ->label('Redemptions')
                    ->state(static function (Campaign $record): string {
                        if ($record->max_redemptions === null) {
                            return (string) $record->current_redemptions;
                        }

                        return "{$record->current_redemptions} / {$record->max_redemptions}";
                    }),

                IconColumn::make('ab_testing_enabled')
                    ->label('A/B')
                    ->boolean()
                    ->tooltip('A/B Testing Enabled'),

                TextColumn::make('variants_count')
                    ->label('Variants')
                    ->counts('variants')
                    ->alignCenter()
                    ->badge()
                    ->color('info')
                    ->visible(fn (): bool => true),

                TextColumn::make('vouchers_count')
                    ->label('Vouchers')
                    ->counts('vouchers')
                    ->alignCenter()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('ends_at')
                    ->label('Ends')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Campaign Type')
                    ->options(
                        static fn (): array => collect(CampaignType::cases())
                            ->mapWithKeys(fn (CampaignType $type): array => [$type->value => $type->label()])
                            ->toArray()
                    ),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(
                        static fn (): array => collect(CampaignStatus::cases())
                            ->mapWithKeys(fn (CampaignStatus $status): array => [$status->value => $status->label()])
                            ->toArray()
                    ),

                SelectFilter::make('objective')
                    ->label('Objective')
                    ->options(
                        static fn (): array => collect(CampaignObjective::cases())
                            ->mapWithKeys(fn (CampaignObjective $obj): array => [$obj->value => $obj->label()])
                            ->toArray()
                    ),

                Filter::make('ab_testing')
                    ->label('A/B Testing Enabled')
                    ->query(static fn (Builder $query): Builder => $query->where('ab_testing_enabled', true)),

                Filter::make('active_now')
                    ->label('Active Now')
                    ->query(
                        static fn (Builder $query): Builder => $query
                            ->where('status', CampaignStatus::Active->value)
                            ->where(
                                fn (Builder $q): Builder => $q
                                    ->whereNull('starts_at')
                                    ->orWhere('starts_at', '<=', now())
                            )
                            ->where(
                                fn (Builder $q): Builder => $q
                                    ->whereNull('ends_at')
                                    ->orWhere('ends_at', '>=', now())
                            )
                    ),

                Filter::make('has_budget_remaining')
                    ->label('Has Budget Remaining')
                    ->query(
                        static fn (Builder $query): Builder => $query
                            ->where(static function (Builder $q): void {
                                $q->whereNull('budget_cents')
                                    ->orWhereRaw('spent_cents < budget_cents');
                            })
                    ),
            ])
            ->actions([
                Action::make('activate')
                    ->icon(Heroicon::OutlinedPlay)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Campaign $record): bool => $record->status->canTransitionTo(CampaignStatus::Active))
                    ->action(fn (Campaign $record) => $record->activate()),

                Action::make('pause')
                    ->icon(Heroicon::OutlinedPause)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Campaign $record): bool => $record->status->canTransitionTo(CampaignStatus::Paused))
                    ->action(fn (Campaign $record) => $record->pause()),

                ViewAction::make()
                    ->icon(Heroicon::OutlinedEye),

                EditAction::make()
                    ->icon(Heroicon::OutlinedPencil),

                DeleteAction::make()
                    ->icon(Heroicon::OutlinedTrash)
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Delete selected')
                        ->icon(Heroicon::OutlinedTrash)
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->poll(static function (): ?string {
                $interval = config('filament-vouchers.polling_interval');

                if ($interval === null || $interval === '') {
                    return null;
                }

                return is_numeric($interval) ? $interval . 's' : (string) $interval;
            })
            ->paginated([25, 50, 100])
            ->striped();
    }
}
