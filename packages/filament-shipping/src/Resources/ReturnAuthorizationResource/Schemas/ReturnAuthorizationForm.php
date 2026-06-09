<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource\Schemas;

use AIArmada\Shipping\Enums\ReturnReason;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ReturnAuthorizationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Return Details')
                    ->schema([
                        TextInput::make('rma_number')
                            ->label('RMA Number')
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('order_reference')
                            ->maxLength(255),

                        Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                'received' => 'Received',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required(),

                        Select::make('type')
                            ->options([
                                'refund' => 'Refund',
                                'exchange' => 'Exchange',
                                'store_credit' => 'Store Credit',
                            ])
                            ->required(),

                        Select::make('reason')
                            ->options(collect(ReturnReason::cases())
                                ->mapWithKeys(fn ($reason) => [$reason->value => $reason->getLabel()]))
                            ->required(),

                        Textarea::make('reason_details')
                            ->rows(3),
                    ])
                    ->columns(2),

                Section::make('Timeline')
                    ->schema([
                        DateTimePicker::make('approved_at')
                            ->disabled(),

                        DateTimePicker::make('received_at')
                            ->disabled(),

                        DateTimePicker::make('completed_at')
                            ->disabled(),

                        DateTimePicker::make('expires_at'),
                    ])
                    ->columns(2),
            ]);
    }
}
