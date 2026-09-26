<?php

declare(strict_types=1);

namespace AIArmada\FilamentPromotions\Resources\PromotionResource\Tables;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\FilamentPromotions\Actions\IssuePromotionVouchersAction;
use AIArmada\Promotions\Enums\PromotionType;
use AIArmada\Promotions\Models\Promotion;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class PromotionsTable
{
    public static function configure(Table $table): Table
    {
        $currency = (string) config('promotions.defaults.currency', 'USD');

        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color('primary')
                    ->placeholder('Auto')
                    ->searchable(),

                TextColumn::make('type')
                    ->badge()
                    ->sortable(),

                TextColumn::make('discount_value')
                    ->label('Discount')
                    ->formatStateUsing(function (Promotion $record) use ($currency): string {
                        if ($record->type->value === 'percentage') {
                            return $record->discount_value . '%';
                        }

                        return MoneyFormatter::formatMinor($record->discount_value, $currency);
                    })
                    ->sortable(),

                TextColumn::make('usage_count')
                    ->label('Uses')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                IconColumn::make('is_currently_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(['is_active']),

                IconColumn::make('is_stackable')
                    ->label('Stack')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('starts_at')
                    ->label('Start')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('ends_at')
                    ->label('End')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('priority')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('priority', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options(collect(PromotionType::cases())->mapWithKeys(
                        fn (PromotionType $type): array => [$type->value => $type->label()]
                    ))
                    ->native(false),

                TernaryFilter::make('is_currently_active')
                    ->label('Active')
                    ->query(static fn (Builder $query, array $data): Builder => self::applyActiveFilter($query, $data)),

                TernaryFilter::make('is_stackable')
                    ->label('Stackable'),

                TernaryFilter::make('has_code')
                    ->label('Has Promo Code')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('code'),
                        false: fn ($query) => $query->whereNull('code'),
                    ),
            ])
            ->actions([
                ...(Promotion::supportsIssuedVoucherTracking() ? [IssuePromotionVouchersAction::make()] : []),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (Promotion $record): void {
                        if (! config('promotions.features.owner.enabled', false)) {
                            return;
                        }

                        OwnerWriteGuard::findOrFailForOwner(
                            Promotion::class,
                            (string) $record->getKey(),
                            OwnerContext::resolve(),
                            includeGlobal: false,
                            message: 'Promotion is not accessible in the current owner scope.',
                        );
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (Collection $records): void {
                            if (! config('promotions.features.owner.enabled', false)) {
                                return;
                            }

                            $owner = OwnerContext::resolve();

                            foreach ($records as $record) {
                                OwnerWriteGuard::findOrFailForOwner(
                                    Promotion::class,
                                    (string) $record->getKey(),
                                    $owner,
                                    includeGlobal: false,
                                    message: 'Promotion is not accessible in the current owner scope.',
                                );
                            }
                        }),
                ]),
            ]);
    }

    /**
     * Match the Active column: "Active" means enabled and not past `ends_at`.
     * Mirrors `Promotion::scopeCurrentlyActive()`; the inverse is written out
     * because there is no `scopeNotCurrentlyActive()`.
     *
     * @param  Builder<Promotion>  $query
     * @return Builder<Promotion>
     */
    private static function applyActiveFilter(Builder $query, array $data): Builder
    {
        return match ($data['state'] ?? null) {
            true => $query->currentlyActive(),
            false => $query->where(function (Builder $query): void {
                $query
                    ->where('is_active', false)
                    ->orWhere(function (Builder $query): void {
                        $query
                            ->where('is_active', true)
                            ->whereNotNull('ends_at')
                            ->where('ends_at', '<=', now());
                    });
            }),
            default => $query,
        };
    }
}
