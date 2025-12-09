<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateResource\Tables;

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use BackedEnum;
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
                    ->color(fn (AffiliateStatus | string $state): string => match ($state instanceof AffiliateStatus ? $state : AffiliateStatus::from($state)) {
                        AffiliateStatus::Active => 'success',
                        AffiliateStatus::Pending => 'warning',
                        AffiliateStatus::Paused => 'gray',
                        AffiliateStatus::Disabled => 'danger',
                        default => 'info',
                    })
                    ->formatStateUsing(fn (AffiliateStatus | string $state): string => $state instanceof AffiliateStatus ? $state->label() : AffiliateStatus::from($state)->label())
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
                    ->options(self::enumOptions(AffiliateStatus::class)),
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

    /**
     * @param  class-string<BackedEnum>  $enum
     * @return array<string, string>
     */
    private static function enumOptions(string $enum): array
    {
        return collect($enum::cases())
            ->mapWithKeys(static fn ($case): array => [$case->value => method_exists($case, 'label') ? $case->label() : ucfirst($case->value)])
            ->toArray();
    }
}
