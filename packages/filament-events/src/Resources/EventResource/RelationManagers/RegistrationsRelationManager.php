<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventResource\RelationManagers;

use AIArmada\Events\States\RegistrationStatus\RegistrationStatus;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Contracts\HasColor;
use Filament\Tables;
use Filament\Tables\Table;

final class RegistrationsRelationManager extends RelationManager
{
    protected static string $relationship = 'registrations';

    protected static ?string $title = 'Registrations';

    protected static ?string $recordTitleAttribute = 'registration_no';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('registration_no')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('registration_type')->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (mixed $state): string | array | null => $state instanceof HasColor ? $state->getColor() : 'gray'),
                Tables\Columns\TextColumn::make('total_participants'),
                Tables\Columns\TextColumn::make('registered_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(RegistrationStatus::options()),
            ])
            ->headerActions([])
            ->actions([
                ViewAction::make(),
            ]);
    }
}
