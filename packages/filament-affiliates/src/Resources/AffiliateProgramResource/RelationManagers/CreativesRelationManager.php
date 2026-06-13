<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateProgramResource\RelationManagers;

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
use Illuminate\Support\Str;

final class CreativesRelationManager extends RelationManager
{
    protected static string $relationship = 'creatives';

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
                ->maxSize(51200)
                ->label('Upload File')
                ->helperText('Upload an image, video, PDF, or ZIP file. Max 50MB.'),

            TextInput::make('asset_url')
                ->label('Asset URL (or leave blank if uploading)')
                ->url()
                ->maxLength(2048)
                ->default(''),

            TextInput::make('destination_url')
                ->required()
                ->url()
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
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),

                TextColumn::make('type')
                    ->badge(),

                TextColumn::make('asset_url')
                    ->label('Asset')
                    ->url(fn (?string $state): ?string => $state, shouldOpenInNewTab: true)
                    ->limit(40),

                TextColumn::make('destination_url')
                    ->label('Destination')
                    ->url(fn (?string $state): ?string => $state, shouldOpenInNewTab: true)
                    ->limit(40),

                TextColumn::make('tracking_code')
                    ->label('Tracking')
                    ->copyable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
