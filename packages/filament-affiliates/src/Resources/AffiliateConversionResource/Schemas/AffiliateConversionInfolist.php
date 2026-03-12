<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;

final class AffiliateConversionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Reference')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('external_reference')
                            ->label('Reference')
                            ->placeholder('—')
                            ->weight(FontWeight::SemiBold)
                            ->copyable(),
                        TextEntry::make('subject_identifier')
                            ->label('Subject Identifier')
                            ->placeholder('—'),
                        TextEntry::make('subject_instance')
                            ->label('Subject Instance')
                            ->placeholder('—'),
                    ]),
                ]),

            Section::make('Cart Integration')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('cart_identifier')
                            ->label('Cart Identifier')
                            ->placeholder('—'),
                        TextEntry::make('cart_instance')
                            ->label('Cart Instance')
                            ->placeholder('—'),
                    ]),
                ])
                ->collapsed(),

            Section::make('Affiliate')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('affiliate.name')
                            ->label('Affiliate Name')
                            ->placeholder('—'),
                        TextEntry::make('affiliate_code')
                            ->label('Affiliate Code')
                            ->badge()
                            ->color('info'),
                        TextEntry::make('voucher_code')
                            ->label('Voucher Code')
                            ->placeholder('—')
                            ->badge()
                            ->color('success'),
                    ]),
                ]),

            Section::make('Amounts')
                ->schema([
                    Grid::make(4)->schema([
                        TextEntry::make('subtotal_minor')
                            ->label('Subtotal')
                            ->formatStateUsing(fn ($state): string => number_format($state / 100, 2) . ' ' . config('affiliates.currency.default', 'MYR'))
                            ->weight(FontWeight::SemiBold),

                        TextEntry::make('total_minor')
                            ->label('Total')
                            ->formatStateUsing(fn ($state): string => number_format($state / 100, 2) . ' ' . config('affiliates.currency.default', 'MYR'))
                            ->weight(FontWeight::SemiBold),

                        TextEntry::make('commission_minor')
                            ->label('Commission')
                            ->formatStateUsing(fn ($state): string => number_format($state / 100, 2) . ' ' . config('affiliates.currency.default', 'MYR'))
                            ->badge()
                            ->color('success')
                            ->weight(FontWeight::Bold),

                        TextEntry::make('commission_currency')
                            ->label('Currency'),
                    ]),
                ]),

            Section::make('Status & Dates')
                ->schema([
                    Grid::make(4)->schema([
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn ($state): string => match ($state?->value ?? $state) {
                                'pending' => 'warning',
                                'qualified' => 'info',
                                'approved' => 'success',
                                'rejected' => 'danger',
                                'paid' => 'success',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn ($state): string => ucfirst($state?->value ?? $state ?? '—')),

                        TextEntry::make('occurred_at')
                            ->label('Occurred At')
                            ->dateTime()
                            ->placeholder('—'),

                        TextEntry::make('approved_at')
                            ->label('Approved At')
                            ->dateTime()
                            ->placeholder('—'),

                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),
                    ]),
                ]),

            Section::make('Metadata')
                ->schema([
                    TextEntry::make('metadata')
                        ->label('Metadata')
                        ->formatStateUsing(fn ($state): string => $state ? json_encode($state, JSON_PRETTY_PRINT) : '—')
                        ->prose()
                        ->markdown(),
                ])
                ->collapsed(),
        ]);
    }
}
