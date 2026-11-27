<?php

declare(strict_types=1);

namespace AIArmada\FilamentContacting\Tables;

use AIArmada\Contacting\Enums\SocialPlatform;
use AIArmada\FilamentContacting\Support\ContactingFilamentConfig;
use AIArmada\FilamentContacting\Support\ContactingLinks;
use AIArmada\FilamentContacting\Support\GuardsContactingUi;
use AIArmada\Contacting\Features\SocialProfiles\Actions\DeleteSocialProfileAction;
use AIArmada\Contacting\Features\SocialProfiles\Actions\UpdateSocialProfileAction;
use AIArmada\Contacting\Features\SocialProfiles\Models\SocialProfile;
use AIArmada\FilamentContacting\Schemas\SocialProfileFormSchema;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use AIArmada\FilamentContacting\Resources\SocialProfileResource;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Throwable;
use Filament\Tables;
use Filament\Tables\Table;

final class SocialProfileTable
{
    public static function table(Table $table): Table
    {
        $guard = app(GuardsContactingUi::class);
        $config = app(ContactingFilamentConfig::class);

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('platform')
                    ->badge()
                    ->searchable(),

                Tables\Columns\TextColumn::make('label')
                    ->searchable(),

                Tables\Columns\TextColumn::make('handle')
                    ->searchable(),

                Tables\Columns\TextColumn::make('url')
                    ->limit(40)
                    ->url(fn (?string $state): ?string => $config->openUrlActions() ? ContactingLinks::safeHttpUrl($state) : null, shouldOpenInNewTab: true)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('display_name')
                    ->searchable(),

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
                    ->visible($config->showVerificationColumns() && $config->verificationBadges()),

                Tables\Columns\TextColumn::make('owner_type')
                    ->label('Owner Type')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible($config->showOwnerColumns()),

                Tables\Columns\TextColumn::make('owner_id')
                    ->label('Owner ID')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible($config->showOwnerColumns()),

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
                Tables\Filters\SelectFilter::make('platform')
                    ->options(SocialPlatform::options()),

                Tables\Filters\TernaryFilter::make('is_primary'),

                Tables\Filters\TernaryFilter::make('is_public'),

                Tables\Filters\TernaryFilter::make('is_verified'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions($config->paginationPageOptions())
            ->defaultPaginationPageOption($config->defaultPagination())
            ->actions([
                ViewAction::make(),

                EditAction::make()
                    ->schema(SocialProfileFormSchema::make())
                    ->using(function (SocialProfile $record, array $data) use ($guard): SocialProfile {
                        abort_if($guard->socialProfilesReadOnly(), 403);

                        return app(UpdateSocialProfileAction::class)->execute($record, $data);
                    })
                    ->visible(fn (): bool => ! $guard->socialProfilesReadOnly())
                    ->disabled(fn (): bool => $guard->socialProfilesReadOnly()),

                DeleteAction::make()
                    ->using(function (SocialProfile $record) use ($guard): bool {
                        abort_if($guard->socialProfilesReadOnly(), 403);

                        return app(DeleteSocialProfileAction::class)->execute($record);
                    })
                    ->visible(fn (): bool => ! $guard->socialProfilesReadOnly())
                    ->disabled(fn (): bool => $guard->socialProfilesReadOnly()),
            ])
            ->bulkActions([
                DeleteBulkAction::make()
                    ->using(function (DeleteBulkAction $action, Collection | LazyCollection $records) use ($guard): void {
                        abort_if($guard->socialProfilesReadOnly(), 403);

                        foreach ($records as $record) {
                            try {
                                if (! SocialProfileResource::canDelete($record) || ! app(DeleteSocialProfileAction::class)->execute($record)) {
                                    $action->reportBulkProcessingFailure();
                                }
                            } catch (Throwable $exception) {
                                $action->reportBulkProcessingFailure();
                                report($exception);
                            }
                        }
                    })
                    ->visible(fn (): bool => ! $guard->socialProfilesReadOnly())
                    ->disabled(fn (): bool => $guard->socialProfilesReadOnly()),

                ExportBulkAction::make()
                    ->visible(fn (): bool => $config->exportsEnabled()),
            ]);
    }
}
