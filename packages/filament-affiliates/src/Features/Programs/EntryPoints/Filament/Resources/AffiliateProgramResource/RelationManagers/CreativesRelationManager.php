<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Resources\AffiliateProgramResource\RelationManagers;

use AIArmada\Affiliates\Features\Programs\Api\CreativeAdministration;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramCreative;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

final class CreativesRelationManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

    protected static string $relationship = 'creatives';

    #[Unexposed]
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof AffiliateProgram && FilamentPermission::hasAbility('affiliate.view');
    }

    #[Unexposed]
    public function getRelationship(): Relation | Builder
    {
        return $this->getOwnerRecord()->hasMany(AffiliateProgramCreative::class, 'program_id');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            Select::make('type')
                ->options([
                    'banner' => 'Banner',
                    'text_link' => 'Text Link',
                    'image' => 'Image',
                    'video' => 'Video',
                    'document' => 'Document',
                    'email' => 'Email Template',
                ])
                ->required()
                ->default('banner'),

            Textarea::make('description')
                ->rows(3),

            SpatieMediaLibraryFileUpload::make('asset')
                ->collection('creative_asset')
                ->maxSize((int) ceil((int) config('media-library.max_file_size', 10485760) / 1024))
                ->label('Upload File')
                ->helperText('Upload an image, video, PDF, or ZIP file.'),

            TextInput::make('destination_url')
                ->url()
                ->helperText('Leave blank if this is a standalone asset with no fixed destination.')
                ->maxLength(2048),

            TextInput::make('tracking_code')
                ->required()
                ->maxLength(255)
                ->default(fn (): string => mb_strtoupper(Str::random(8))),

            TextInput::make('width')
                ->numeric()
                ->nullable(),

            TextInput::make('height')
                ->numeric()
                ->nullable(),

            KeyValue::make('metadata')
                ->keyLabel('Key')
                ->valueLabel('Value')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('media'))
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),

                TextColumn::make('type')
                    ->badge(),

                TextColumn::make('asset')
                    ->state(fn (AffiliateProgramCreative $record): ?string => $record->getAssetUrl())
                    ->label('Asset')
                    ->url(fn (?string $state): ?string => $state, shouldOpenInNewTab: true)
                    ->limit(40),

                TextColumn::make('destination_url')
                    ->state(fn (AffiliateProgramCreative $record): ?string => $record->safeDestinationUrl())
                    ->label('Destination')
                    ->url(fn (?string $state): ?string => $state, shouldOpenInNewTab: true)
                    ->limit(40),

                TextColumn::make('tracking_code')
                    ->label('Tracking')
                    ->copyable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->authorize(fn (): bool => ! $this->isReadOnly() && FilamentPermission::hasAbility('affiliate.update'))
                    ->using(function (array $data): AffiliateProgramCreative {
                        $id = app(CreativeAdministration::class)->create((string) $this->getOwnerRecord()->getKey(), $data);

                        return AffiliateProgramCreative::query()->findOrFail($id);
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->authorize(fn (): bool => ! $this->isReadOnly() && FilamentPermission::hasAbility('affiliate.update'))
                    ->using(function (AffiliateProgramCreative $record, array $data): AffiliateProgramCreative {
                        app(CreativeAdministration::class)->update((string) $this->getOwnerRecord()->getKey(), (string) $record->getKey(), $data);

                        return $record->refresh();
                    }),
                DeleteAction::make()
                    ->authorize(fn (): bool => ! $this->isReadOnly() && FilamentPermission::hasAbility('affiliate.update'))
                    ->using(fn (AffiliateProgramCreative $record): bool => app(CreativeAdministration::class)->delete((string) $this->getOwnerRecord()->getKey(), (string) $record->getKey())),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorize(fn (): bool => ! $this->isReadOnly() && FilamentPermission::hasAbility('affiliate.update'))
                        ->action(function ($records): void {
                            foreach ($records as $record) {
                                app(CreativeAdministration::class)->delete((string) $this->getOwnerRecord()->getKey(), (string) $record->getKey());
                            }
                        }),
                ]),
            ]);
    }
}
