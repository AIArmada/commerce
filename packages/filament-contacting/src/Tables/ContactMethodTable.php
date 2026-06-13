<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Tables;

use AIArmada\Contacting\Enums\ContactMethodType;
use AIArmada\FilamentContacting\Support\ContactingFilamentConfig;
use AIArmada\FilamentContacting\Support\GuardsContactingUi;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables;
use Filament\Tables\Table;

final class ContactMethodTable
{
    public static function table(Table $table): Table
    {
        $guard = app(GuardsContactingUi::class);
        $config = app(ContactingFilamentConfig::class);

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->searchable(),

                Tables\Columns\TextColumn::make('label')
                    ->searchable(),

                Tables\Columns\TextColumn::make('display_value')
                    ->label('Display Value')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('value')
                    ->searchable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('country_code'),

                Tables\Columns\IconColumn::make('is_primary')
                    ->boolean()
                    ->label('Primary'),

                Tables\Columns\IconColumn::make('is_public')
                    ->boolean()
                    ->label('Public')
                    ->visible($config->showVisibilityColumns()),

                Tables\Columns\IconColumn::make('is_verified')
                    ->boolean()
                    ->label('Verified')
                    ->visible($config->showVerificationColumns()),

                Tables\Columns\TextColumn::make('verified_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(ContactMethodType::options(config('contacting.contact_methods.types', []))),

                Tables\Filters\TernaryFilter::make('is_primary'),

                Tables\Filters\TernaryFilter::make('is_public'),

                Tables\Filters\TernaryFilter::make('is_verified'),

                Tables\Filters\SelectFilter::make('country_code'),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                ViewAction::make(),

                EditAction::make()
                    ->visible(fn (): bool => ! $guard->contactMethodsReadOnly()),

                DeleteAction::make()
                    ->visible(fn (): bool => ! $guard->contactMethodsReadOnly()),
            ])
            ->bulkActions([
                DeleteBulkAction::make()
                    ->visible(fn (): bool => ! $guard->contactMethodsReadOnly()),

                ExportBulkAction::make()
                    ->visible(fn (): bool => $config->exportsEnabled()),
            ]);
    }
}
