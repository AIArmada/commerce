<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class AffiliateConversionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Reference')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('order_reference')
                            ->label('Order Reference')
                            ->readOnly(),
                        TextInput::make('cart_identifier')
                            ->label('Cart Identifier')
                            ->readOnly(),
                        TextInput::make('cart_instance')
                            ->label('Instance')
                            ->readOnly(),
                    ]),
                ]),

            Section::make('Amounts')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('subtotal_minor')
                            ->label('Subtotal (minor)')
                            ->numeric()
                            ->readOnly(),

                        TextInput::make('total_minor')
                            ->label('Total (minor)')
                            ->numeric()
                            ->readOnly(),

                        TextInput::make('commission_minor')
                            ->label('Commission (minor)')
                            ->numeric()
                            ->readOnly(),
                    ]),

                    Grid::make(2)->schema([
                        TextInput::make('commission_currency')
                            ->label('Currency')
                            ->readOnly(),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending' => 'Pending',
                                'qualified' => 'Qualified',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                'paid' => 'Paid',
                            ]),
                    ]),
                ])
                ->collapsed(),

            Section::make('Metadata')
                ->schema([
                    Textarea::make('metadata')
                        ->label('Metadata (JSON)')
                        ->rows(4)
                        ->formatStateUsing(fn ($state): ?string => $state ? json_encode($state, JSON_PRETTY_PRINT) : null)
                        ->dehydrateStateUsing(static function (?string $state): ?array {
                            if (! $state) {
                                return null;
                            }

                            $decoded = json_decode($state, true);

                            return is_array($decoded) ? $decoded : null;
                        }),
                ])
                ->collapsed(),
        ]);
    }
}
