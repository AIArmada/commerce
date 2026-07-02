<?php

declare(strict_types=1);

namespace AIArmada\FilamentTicketing\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Ticketing\Models\Pass;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class PassResource extends Resource
{
    protected static ?string $model = Pass::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-ticket';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-ticketing.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-ticketing.resources.navigation_sort.pass');

        return is_numeric($sort) ? (int) $sort : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return OwnerUiScope::apply(parent::getEloquentQuery(), includeGlobal: false)
            ->with(['ticketType', 'holder']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('pass_no')->searchable(),
                Tables\Columns\TextColumn::make('ticketType.name')->label('Ticket Type'),
                Tables\Columns\TextColumn::make('holder.name')->label('Holder'),
                Tables\Columns\TextColumn::make('holder.email')->label('Email'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'issued' => 'success',
                        'activated' => 'info',
                        'used' => 'warning',
                        'cancelled', 'revoked', 'voided' => 'danger',
                        'expired' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('issued_at')->dateTime(),
                Tables\Columns\TextColumn::make('used_at')->dateTime(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'issued' => 'Issued',
                        'activated' => 'Activated',
                        'used' => 'Used',
                        'cancelled' => 'Cancelled',
                        'revoked' => 'Revoked',
                        'voided' => 'Voided',
                        'expired' => 'Expired',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Pass Details')
                    ->schema([
                        TextEntry::make('pass_no'),
                        TextEntry::make('ticketType.name')->label('Ticket Type'),
                        TextEntry::make('holder.name')->label('Holder Name'),
                        TextEntry::make('holder.email')->label('Holder Email'),
                        TextEntry::make('qr_code')->label('QR Code'),
                        TextEntry::make('barcode')->label('Barcode'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('issued_at')->dateTime(),
                        TextEntry::make('activated_at')->dateTime(),
                        TextEntry::make('used_at')->dateTime(),
                        TextEntry::make('cancelled_at')->dateTime(),
                        TextEntry::make('revoked_at')->dateTime(),
                        TextEntry::make('voided_at')->dateTime(),
                        TextEntry::make('expired_at')->dateTime(),
                        TextEntry::make('transfer_expires_at')->dateTime(),
                        TextEntry::make('status_reason'),
                    ])->columns(2),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => PassResource\Pages\ListPasses::route('/'),
            'view' => PassResource\Pages\ViewPass::route('/{record}'),
        ];
    }
}
