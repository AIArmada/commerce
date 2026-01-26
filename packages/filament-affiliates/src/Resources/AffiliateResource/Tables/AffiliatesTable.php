<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateResource\Tables;

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\AffiliateStatus;
use AIArmada\Affiliates\States\Disabled;
use AIArmada\Affiliates\States\Draft;
use AIArmada\Affiliates\States\Paused;
use AIArmada\Affiliates\States\Pending;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class AffiliatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->icon(Heroicon::OutlinedLink)
                    ->copyable()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Name')
                    ->description(fn (Affiliate $record): ?string => $record->default_voucher_code ? "Voucher: {$record->default_voucher_code}" : null)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(function (AffiliateStatus | string $state): string {
                        $status = AffiliateStatus::fromString($state);

                        return match (true) {
                            $status instanceof Draft => 'gray',
                            $status instanceof Active => 'success',
                            $status instanceof Pending => 'warning',
                            $status instanceof Paused => 'gray',
                            $status instanceof Disabled => 'danger',
                            default => 'info',
                        };
                    })
                    ->formatStateUsing(fn (AffiliateStatus | string $state): string => AffiliateStatus::fromString($state)->label())
                    ->sortable(),

                TextColumn::make('commission_rate')
                    ->label('Commission')
                    ->state(function (Affiliate $record): string {
                        $type = $record->commission_type instanceof CommissionType
                            ? $record->commission_type
                            : CommissionType::from((string) $record->commission_type);

                        $value = (int) $record->commission_rate;

                        return $type === CommissionType::Percentage
                            ? number_format($value / 100, 2) . ' %'
                            : sprintf('%s %.2f', $record->currency, $value / 100);
                    })
                    ->badge()
                    ->color('primary'),

                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(AffiliateStatus::options()),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }
}
