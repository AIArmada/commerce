<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Tables;

use AIArmada\Contacting\Enums\ContactMethodType;
use AIArmada\Contacting\Enums\SocialPlatform;
use AIArmada\FilamentContacting\Support\ContactingFilamentConfig;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class ContactSnapshotTable
{
    public static function table(Table $table): Table
    {
        $config = app(ContactingFilamentConfig::class);

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('snapshot_type')
                    ->badge()
                    ->label('Type'),

                Tables\Columns\TextColumn::make('reason')
                    ->searchable(),

                Tables\Columns\TextColumn::make('snapshotable_type')
                    ->label('Source Entity'),

                Tables\Columns\TextColumn::make('channel')
                    ->badge(),

                Tables\Columns\TextColumn::make('value')
                    ->limit(50),

                Tables\Columns\TextColumn::make('owner_type')
                    ->label('Owner Type')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible($config->showOwnerColumns()),

                Tables\Columns\TextColumn::make('owner_id')
                    ->label('Owner ID')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible($config->showOwnerColumns()),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('snapshot_type')
                    ->options([
                        'contact_method' => 'Contact Method',
                        'social_profile' => 'Social Profile',
                    ]),

                Tables\Filters\Filter::make('reason')
                    ->form([
                        TextInput::make('value')
                            ->label('Reason')
                            ->maxLength(255),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, string $value): Builder => $query->where('reason', $value),
                    )),

                Tables\Filters\SelectFilter::make('channel')
                    ->options(fn (): array => ContactMethodType::options(config('contacting.contact_methods.types', [])) + SocialPlatform::options()),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions($config->paginationPageOptions())
            ->defaultPaginationPageOption($config->defaultPagination())
            ->actions([
                ViewAction::make(),
            ]);
    }
}
