<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\VoucherResource\Tables;

use AIArmada\Affiliates\Features\Programs\Api\ProgramReferenceReader;
use AIArmada\FilamentVouchers\Resources\VoucherResource;
use AIArmada\FilamentVouchers\Support\ConditionTargetDisplay;
use AIArmada\FilamentVouchers\Support\MoneyHelper;
use AIArmada\Vouchers\Data\VoucherType;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherDeletion;
use AIArmada\Vouchers\Features\Lifecycle\Models\Voucher;
use AIArmada\Vouchers\Features\Lifecycle\States\Active;
use AIArmada\Vouchers\Features\Lifecycle\States\Depleted;
use AIArmada\Vouchers\Features\Lifecycle\States\Expired;
use AIArmada\Vouchers\Features\Lifecycle\States\Paused;
use AIArmada\Vouchers\Features\Lifecycle\States\VoucherStatus;
use Carbon\CarbonImmutable;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use WeakMap;

final class VouchersTable
{
    public static function configure(Table $table): Table
    {
        $programLabels = new WeakMap;

        return $table
            ->modifyQueryUsing(static function (Builder $query): Builder {
                $query->withCount('usages');

                // The owner column reads the morphTo relation per row.
                $query->with('owner');

                if (class_exists('AIArmada\\Promotions\\Models\\Promotion')) {
                    $query->with('promotion');
                }

                return $query;
            })
            ->columns(array_values(array_filter([
                TextColumn::make('code')
                    ->label('Code')
                    ->copyable()
                    ->searchable()
                    ->sortable()
                    ->icon(Heroicon::Tag),

                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('promotion_source_label')
                    ->label('Source Promotion')
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(static fn (VoucherType | string $state): string => match ($state instanceof VoucherType ? $state : VoucherType::from($state)) {
                        VoucherType::Percentage => 'primary',
                        VoucherType::Fixed => 'success',
                        VoucherType::FreeShipping => 'warning',
                        VoucherType::BuyXGetY => 'info',
                        VoucherType::Tiered => 'secondary',
                        VoucherType::Bundle => 'primary',
                        VoucherType::Cashback => 'success',
                    })
                    ->formatStateUsing(static fn (VoucherType | string $state): string => $state instanceof VoucherType ? $state->label() : VoucherType::from($state)->label())
                    ->sortable(),

                TextColumn::make('value')
                    ->label('Value')
                    ->formatStateUsing(static function ($state, Voucher $record): string {
                        $rawType = $record->type;
                        $type = $rawType instanceof VoucherType ? $rawType : VoucherType::from((string) $rawType);

                        if ($type === VoucherType::Percentage) {
                            return MoneyHelper::formatPercentage((int) $state);
                        }

                        return MoneyHelper::formatMoney((int) $state, (string) $record->currency);
                    })
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('condition_target_display')
                    ->label('Target')
                    ->state(static fn (Voucher $record): string => ConditionTargetDisplay::presetLabel($record->target_definition))
                    ->tooltip(static fn (Voucher $record): ?string => ConditionTargetDisplay::dsl($record->target_definition))
                    ->badge()
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('usages_count')
                    ->label('Redeemed')
                    ->counts('usages')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('applied_count')
                    ->label('Applied')
                    ->alignCenter()
                    ->sortable()
                    ->tooltip('Number of times this voucher has been applied to carts'),

                TextColumn::make('usageProgress')
                    ->label('Usage %')
                    ->state(fn (Voucher $record): ?float => $record->usageProgress)
                    ->formatStateUsing(static fn (?float $state): string => $state === null ? '—' : number_format($state, 1) . '%')
                    ->badge()
                    ->color('success')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('remaining_uses')
                    ->label('Remaining')
                    ->state(fn (Voucher $record): string => Str::of((string) ($record->getRemainingUses() ?? '∞'))->toString())
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('owner_display_name')
                    ->label('Owner')
                    ->placeholder('Global')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),

                interface_exists(ProgramReferenceReader::class) && app()->bound(ProgramReferenceReader::class)
                    ? TextColumn::make('affiliate_program_name')
                        ->label('Program')
                        ->state(static function (Voucher $record, HasTable $livewire) use ($programLabels): ?string {
                            if ($record->affiliate_program_id === null) {
                                return null;
                            }

                            $records = $livewire->getTableRecords();

                            if (! isset($programLabels[$records])) {
                                $ids = [];

                                foreach ($records as $voucher) {
                                    if ($voucher instanceof Voucher && $voucher->affiliate_program_id !== null) {
                                        $ids[] = $voucher->affiliate_program_id;
                                    }
                                }

                                $programLabels[$records] = app(ProgramReferenceReader::class)->labels(array_values(array_unique($ids)));
                            }

                            return $programLabels[$records][$record->affiliate_program_id] ?? null;
                        })
                        ->placeholder('—')
                        ->toggleable(isToggledHiddenByDefault: true)
                    : null,

                TextColumn::make('effective_status')
                    ->label('Status')
                    ->badge()
                    ->color(static function (VoucherStatus | string $state): string {
                        $status = $state instanceof VoucherStatus ? $state : VoucherStatus::fromString($state);

                        return match (true) {
                            $status instanceof Active => 'success',
                            $status instanceof Paused => 'warning',
                            $status instanceof Expired => 'danger',
                            $status instanceof Depleted => 'gray',
                            default => 'gray',
                        };
                    })
                    ->formatStateUsing(static fn (VoucherStatus | string $state): string => VoucherStatus::labelFor($state))
                    ->sortable(['status']),

                IconColumn::make('allows_manual_redemption')
                    ->label('Manual?')
                    ->boolean()
                    ->tooltip('Allow staff to redeem this voucher manually'),

                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])))
            ->filters([
                SelectFilter::make('type')
                    ->label('Voucher Type')
                    ->options(static fn (): array => collect(VoucherType::cases())->mapWithKeys(fn (VoucherType $type): array => [$type->value => $type->label()])->toArray()),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(static fn (): array => VoucherStatus::options())
                    ->query(static function ($query, array $data): mixed {
                        $status = $data['value'] ?? null;

                        if ($status === null) {
                            return $query;
                        }

                        if ($status !== VoucherStatus::normalize(Expired::class)) {
                            return $query->where('status', $status);
                        }

                        // A past-due voucher reads as Expired even when the stored
                        // status is still active or paused, so match the same set
                        // `getEffectiveStatusAttribute()` reports.
                        return $query->where(function ($builder): void {
                            $builder
                                ->where('status', VoucherStatus::normalize(Expired::class))
                                ->orWhere(function ($builder): void {
                                    $builder
                                        ->whereNotIn('status', [
                                            VoucherStatus::normalize(Expired::class),
                                            VoucherStatus::normalize(Depleted::class),
                                        ])
                                        ->whereNotNull('expires_at')
                                        ->where('expires_at', '<=', now());
                                });
                        });
                    }),

                Filter::make('manual_only')
                    ->label('Manual Redemption')
                    ->query(static fn ($query) => $query->where('allows_manual_redemption', true)),

                Filter::make('active_now')
                    ->label('Active right now')
                    ->query(static function ($query) {
                        $now = CarbonImmutable::now();

                        return $query
                            ->where('status', VoucherStatus::normalize(Active::class))
                            ->where(function ($builder) use ($now): void {
                                $builder
                                    ->whereNull('starts_at')
                                    ->orWhere('starts_at', '<=', $now);
                            })
                            ->where(function ($builder) use ($now): void {
                                $builder
                                    ->whereNull('expires_at')
                                    ->orWhere('expires_at', '>=', $now);
                            });
                    }),
            ])
            ->actions([
                ViewAction::make()
                    ->icon(Heroicon::OutlinedEye),

                EditAction::make()
                    ->icon(Heroicon::OutlinedPencil),

                DeleteAction::make()
                    ->using(fn (Voucher $record): bool => app(VoucherDeletion::class)->handle((string) $record->getKey()))
                    ->icon(Heroicon::OutlinedTrash)
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function (Collection $records): void {
                            foreach ($records as $record) {
                                abort_unless(VoucherResource::canDelete($record), 403);
                            }

                            app(VoucherDeletion::class)->handleMany($records->map(static fn (Voucher $record): string => (string) $record->getKey())->values()->all());
                        })
                        ->label('Delete selected')
                        ->icon(Heroicon::OutlinedTrash)
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->poll(static function () {
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
