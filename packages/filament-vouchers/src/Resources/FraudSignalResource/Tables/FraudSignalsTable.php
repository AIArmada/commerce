<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\FraudSignalResource\Tables;

use AIArmada\FilamentVouchers\Actions\MarkFraudReviewedAction;
use AIArmada\Vouchers\Fraud\Enums\FraudRiskLevel;
use AIArmada\Vouchers\Fraud\Enums\FraudSignalType;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class FraudSignalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('voucher_code')
                    ->label('Voucher')
                    ->copyable()
                    ->searchable()
                    ->sortable()
                    ->icon(Heroicon::Ticket),

                TextColumn::make('signal_type')
                    ->label('Signal')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(static fn (FraudSignalType | string $state): string => $state instanceof FraudSignalType ? $state->getLabel() : FraudSignalType::from($state)->getLabel())
                    ->tooltip(static fn (FraudSignalType | string $state): string => $state instanceof FraudSignalType ? $state->getDescription() : FraudSignalType::from($state)->getDescription()),

                TextColumn::make('risk_level')
                    ->label('Risk')
                    ->badge()
                    ->color(static fn (FraudRiskLevel | string $state): string => match ($state instanceof FraudRiskLevel ? $state : FraudRiskLevel::from($state)) {
                        FraudRiskLevel::Low => 'success',
                        FraudRiskLevel::Medium => 'warning',
                        FraudRiskLevel::High => 'danger',
                        FraudRiskLevel::Critical => 'danger',
                    })
                    ->formatStateUsing(static fn (FraudRiskLevel | string $state): string => $state instanceof FraudRiskLevel ? $state->getLabel() : FraudRiskLevel::from($state)->getLabel())
                    ->sortable(),

                TextColumn::make('score')
                    ->label('Score')
                    ->formatStateUsing(static fn (float $state): string => number_format($state * 100, 0) . '%')
                    ->sortable(),

                TextColumn::make('detector')
                    ->label('Detector')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('message')
                    ->label('Message')
                    ->limit(50)
                    ->wrap(),

                IconColumn::make('was_blocked')
                    ->label('Blocked')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedXCircle)
                    ->trueColor('danger'),

                IconColumn::make('reviewed')
                    ->label('Reviewed')
                    ->boolean(),

                TextColumn::make('ip_address')
                    ->label('IP')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Detected')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('risk_level')
                    ->label('Risk Level')
                    ->options(static fn (): array => collect(FraudRiskLevel::cases())
                        ->mapWithKeys(fn (FraudRiskLevel $level): array => [$level->value => $level->getLabel()])
                        ->toArray()),

                SelectFilter::make('signal_type')
                    ->label('Signal Type')
                    ->options(static fn (): array => collect(FraudSignalType::cases())
                        ->mapWithKeys(fn (FraudSignalType $type): array => [$type->value => $type->getLabel()])
                        ->toArray())
                    ->searchable(),

                Filter::make('unreviewed')
                    ->label('Needs Review')
                    ->query(static fn (Builder $query): Builder => $query->where('reviewed', false)),

                Filter::make('blocked')
                    ->label('Was Blocked')
                    ->query(static fn (Builder $query): Builder => $query->where('was_blocked', true)),

                Filter::make('high_risk')
                    ->label('High/Critical Risk')
                    ->query(static fn (Builder $query): Builder => $query->whereIn('risk_level', [
                        FraudRiskLevel::High->value,
                        FraudRiskLevel::Critical->value,
                    ])),
            ])
            ->actions([
                MarkFraudReviewedAction::make(),
                ViewAction::make()
                    ->icon(Heroicon::OutlinedEye),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s')
            ->paginated([25, 50, 100])
            ->striped();
    }
}
