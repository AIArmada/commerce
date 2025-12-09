<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\CampaignResource\Schemas;

use AIArmada\Vouchers\Campaigns\Enums\CampaignStatus;
use Akaunting\Money\Money;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class CampaignInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Campaign Overview')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('name')
                            ->label('Campaign Name')
                            ->weight('bold'),

                        TextEntry::make('slug')
                            ->label('Slug')
                            ->badge()
                            ->color('gray'),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (CampaignStatus $state): string => match ($state) {
                                CampaignStatus::Draft => 'gray',
                                CampaignStatus::Scheduled => 'info',
                                CampaignStatus::Active => 'success',
                                CampaignStatus::Paused => 'warning',
                                CampaignStatus::Completed => 'primary',
                                CampaignStatus::Cancelled => 'danger',
                            }),
                    ]),

                    TextEntry::make('description')
                        ->label('Description')
                        ->html()
                        ->columnSpanFull()
                        ->visible(fn ($record): bool => ! empty($record->description)),

                    Grid::make(2)->schema([
                        TextEntry::make('type')
                            ->label('Campaign Type')
                            ->badge()
                            ->color('primary'),

                        TextEntry::make('objective')
                            ->label('Objective')
                            ->badge()
                            ->color('info'),
                    ]),
                ]),

            Section::make('Schedule')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('starts_at')
                            ->label('Start Date')
                            ->dateTime()
                            ->placeholder('Not scheduled'),

                        TextEntry::make('ends_at')
                            ->label('End Date')
                            ->dateTime()
                            ->placeholder('No end date'),

                        TextEntry::make('timezone')
                            ->label('Timezone'),
                    ]),
                ])
                ->collapsible(),

            Section::make('Budget & Performance')
                ->schema([
                    Grid::make(4)->schema([
                        TextEntry::make('budget_cents')
                            ->label('Budget')
                            ->formatStateUsing(static function (?int $state): string {
                                $currency = mb_strtoupper((string) config('filament-vouchers.default_currency', 'MYR'));

                                return $state !== null
                                    ? (string) Money::{$currency}($state)
                                    : 'Unlimited';
                            }),

                        TextEntry::make('spent_cents')
                            ->label('Spent')
                            ->formatStateUsing(static function (?int $state): string {
                                $currency = mb_strtoupper((string) config('filament-vouchers.default_currency', 'MYR'));

                                return (string) Money::{$currency}($state ?? 0);
                            }),

                        TextEntry::make('budget_utilization')
                            ->label('Utilization')
                            ->formatStateUsing(
                                fn (?float $state): string => $state !== null
                                ? number_format($state, 1) . '%'
                                : 'N/A'
                            )
                            ->badge()
                            ->color(fn (?float $state): string => match (true) {
                                $state === null => 'gray',
                                $state >= 90 => 'danger',
                                $state >= 70 => 'warning',
                                default => 'success',
                            }),

                        TextEntry::make('remaining_budget')
                            ->label('Remaining')
                            ->formatStateUsing(static function (?int $state): string {
                                $currency = mb_strtoupper((string) config('filament-vouchers.default_currency', 'MYR'));

                                return $state !== null
                                    ? (string) Money::{$currency}($state)
                                    : 'Unlimited';
                            }),
                    ]),

                    Grid::make(3)->schema([
                        TextEntry::make('max_redemptions')
                            ->label('Max Redemptions')
                            ->formatStateUsing(
                                fn (?int $state): string => $state !== null
                                ? number_format($state)
                                : 'Unlimited'
                            ),

                        TextEntry::make('current_redemptions')
                            ->label('Current Redemptions')
                            ->formatStateUsing(fn (int $state): string => number_format($state)),

                        TextEntry::make('remaining_redemptions')
                            ->label('Remaining')
                            ->formatStateUsing(
                                fn (?int $state): string => $state !== null
                                ? number_format($state)
                                : 'Unlimited'
                            ),
                    ]),
                ])
                ->collapsible(),

            Section::make('A/B Testing')
                ->schema([
                    Grid::make(3)->schema([
                        IconEntry::make('ab_testing_enabled')
                            ->label('A/B Testing Enabled')
                            ->boolean(),

                        TextEntry::make('ab_winner_variant')
                            ->label('Winning Variant')
                            ->placeholder('Not declared')
                            ->badge()
                            ->color('success'),

                        TextEntry::make('ab_winner_declared_at')
                            ->label('Winner Declared')
                            ->dateTime()
                            ->placeholder('Not declared'),
                    ]),

                    TextEntry::make('variants_count')
                        ->label('Total Variants')
                        ->state(fn ($record): int => $record->variants()->count())
                        ->badge()
                        ->color('info'),
                ])
                ->visible(fn ($record): bool => (bool) $record->ab_testing_enabled)
                ->collapsible(),

            Section::make('Metadata')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),

                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime(),
                    ]),

                    TextEntry::make('owner_display')
                        ->label('Owner')
                        ->state(static function ($record): string {
                            if ($record->owner_type === null) {
                                return 'Global';
                            }

                            $owner = $record->owner;

                            return $owner !== null ? $owner->name : $record->owner_type;
                        }),
                ])
                ->collapsed(),
        ]);
    }
}
