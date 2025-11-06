<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\VoucherResource\Schemas;

use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class VoucherInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Voucher Overview')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('code')
                                ->label('Code')
                                ->copyable()
                                ->badge(),

                            TextEntry::make('name')
                                ->label('Name'),
                        ]),

                    Grid::make(3)
                        ->schema([
                            TextEntry::make('type')
                                ->label('Type')
                                ->formatStateUsing(static fn (VoucherType|string $state): string => $state instanceof VoucherType ? $state->label() : VoucherType::from($state)->label())
                                ->badge(),

                            TextEntry::make('value_label')
                                ->label('Value'),

                            TextEntry::make('status')
                                ->label('Status')
                                ->formatStateUsing(static fn (VoucherStatus|string $state): string => $state instanceof VoucherStatus ? $state->label() : VoucherStatus::from($state)->label())
                                ->badge(),
                        ]),

                    Grid::make(3)
                        ->schema([
                            TextEntry::make('starts_at')
                                ->label('Starts')
                                ->dateTime(),

                            TextEntry::make('expires_at')
                                ->label('Expires')
                                ->dateTime(),

                            TextEntry::make('owner_display_name')
                                ->label('Owner')
                                ->default('Global'),
                        ]),
                ]),

            Section::make('Usage Metrics')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('times_used')
                                ->label('Redeemed')
                                ->badge(),

                            TextEntry::make('remaining_uses')
                                ->label('Remaining')
                                ->state(static fn ($record): string => Str::of((string) ($record->getRemainingUses() ?? '∞'))->toString())
                                ->badge(),

                            TextEntry::make('usageProgress')
                                ->label('Usage %')
                                ->state(static fn ($record): string => $record->usageProgress === null ? '—' : number_format($record->usageProgress, 1).'%')
                                ->badge(),
                        ]),
                ]),

            Section::make('Description')
                ->schema([
                    TextEntry::make('description')
                        ->label('Description')
                        ->markdown()
                        ->default('-'),
                ]),
        ]);
    }
}
