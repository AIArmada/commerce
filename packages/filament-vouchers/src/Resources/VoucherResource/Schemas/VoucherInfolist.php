<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\VoucherResource\Schemas;

use AIArmada\Cart\Conditions\ConditionTarget;
use AIArmada\FilamentVouchers\Support\ConditionTargetPreset;
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
                                ->formatStateUsing(static fn (VoucherType | string $state): string => $state instanceof VoucherType ? $state->label() : VoucherType::from($state)->label())
                                ->badge(),

                            TextEntry::make('value_label')
                                ->label('Value'),

                            TextEntry::make('status')
                                ->label('Status')
                                ->formatStateUsing(static fn (VoucherStatus | string $state): string => $state instanceof VoucherStatus ? $state->label() : VoucherStatus::from($state)->label())
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

            Section::make('Condition Target')
                ->schema([
                    Grid::make(4)
                        ->schema([
                            TextEntry::make('condition_target_preset')
                                ->label('Preset')
                                ->state(static function ($record): string {
                                    $metadata = is_array($record->metadata ?? null) ? $record->metadata : [];
                                    $definition = $record->target_definition
                                        ?? ($metadata['target_definition'] ?? null);
                                    $dsl = is_array($definition)
                                        ? ConditionTarget::from($definition)->toDsl()
                                        : ConditionTargetPreset::default()->dsl();
                                    $preset = ConditionTargetPreset::detect($dsl);

                                    return ($preset ?? ConditionTargetPreset::Custom)->label();
                                })
                                ->badge(),

                            TextEntry::make('condition_target_scope')
                                ->label('Scope')
                                ->state(static function ($record): string {
                                    $metadata = is_array($record->metadata ?? null) ? $record->metadata : [];
                                    $scope = data_get($record, 'target_definition.scope')
                                        ?? data_get($metadata, 'target_definition.scope')
                                        ?? 'cart';

                                    return mb_strtoupper((string) $scope);
                                })
                                ->badge(),

                            TextEntry::make('condition_target_phase')
                                ->label('Phase')
                                ->state(static function ($record): string {
                                    $metadata = is_array($record->metadata ?? null) ? $record->metadata : [];
                                    $phase = data_get($record, 'target_definition.phase')
                                        ?? data_get($metadata, 'target_definition.phase')
                                        ?? 'cart_subtotal';

                                    return str_replace('_', ' ', (string) $phase);
                                })
                                ->badge(),

                            TextEntry::make('condition_target_application')
                                ->label('Application')
                                ->state(static function ($record): string {
                                    $metadata = is_array($record->metadata ?? null) ? $record->metadata : [];
                                    $application = data_get($record, 'target_definition.application')
                                        ?? data_get($metadata, 'target_definition.application')
                                        ?? 'aggregate';

                                    return str_replace('_', ' ', (string) $application);
                                })
                                ->badge(),

                            TextEntry::make('condition_target_dsl_display')
                                ->label('Target DSL')
                                ->state(static function ($record): string {
                                    $metadata = is_array($record->metadata ?? null) ? $record->metadata : [];
                                    $definition = $record->target_definition
                                        ?? ($metadata['target_definition'] ?? null);

                                    return $definition !== null
                                        ? ConditionTarget::from($definition)->toDsl()
                                        : ConditionTargetPreset::default()->dsl();
                                })
                                ->copyable()
                                ->formatStateUsing(static fn (string $state): string => $state)
                                ->columnSpanFull(),
                        ]),
                ])
                ->collapsible(),

            Section::make('Usage Metrics')
                ->schema([
                    Grid::make(4)
                        ->schema([
                            TextEntry::make('applied_count')
                                ->label('Applied')
                                ->badge()
                                ->tooltip('Number of times this voucher has been applied to carts'),

                            TextEntry::make('usages_count')
                                ->label('Redeemed')
                                ->state(static fn ($record): int => $record->usages()->count())
                                ->badge(),

                            TextEntry::make('remaining_uses')
                                ->label('Remaining')
                                ->state(static fn ($record): string => Str::of((string) ($record->getRemainingUses() ?? '∞'))->toString())
                                ->badge(),

                            TextEntry::make('usageProgress')
                                ->label('Usage %')
                                ->state(static fn ($record): string => $record->usageProgress === null ? '—' : number_format($record->usageProgress, 1) . '%')
                                ->badge(),
                        ]),
                ]),

            Section::make('Wallet Statistics')
                ->description('Vouchers saved to user wallets for future use')
                ->schema([
                    Grid::make(4)
                        ->schema([
                            TextEntry::make('wallet_entries_count')
                                ->label('Total in Wallets')
                                ->badge()
                                ->color('primary'),

                            TextEntry::make('wallet_available_count')
                                ->label('Available')
                                ->badge()
                                ->color('success'),

                            TextEntry::make('wallet_claimed_count')
                                ->label('Claimed')
                                ->badge()
                                ->color('warning'),

                            TextEntry::make('wallet_redeemed_count')
                                ->label('Redeemed')
                                ->badge()
                                ->color('danger'),
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
