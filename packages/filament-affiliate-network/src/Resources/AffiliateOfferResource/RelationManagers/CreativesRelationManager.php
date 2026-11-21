<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers;

use AIArmada\AffiliateNetwork\Actions\CreateCreative;
use AIArmada\AffiliateNetwork\Actions\DeleteCreative;
use AIArmada\AffiliateNetwork\Actions\UpdateCreative;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferCreative;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Models\Concerns\ScopesByBelongsToOwner;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliateNetwork\Support\NetworkAdminAccess;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class CreativesRelationManager extends RelationManager
{
    protected static string $relationship = 'creatives';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return NetworkAdminAccess::allows();
    }

    public function form(Schema $schema): Schema
    {
        $imported = fn (?AffiliateOfferCreative $record): bool => $record?->external_creative_id !== null;

        return $schema->components([
            TextInput::make('name')->required()->maxLength(255)->disabled($imported),
            Select::make('type')->options([
                'banner' => 'Banner', 'text' => 'Text link', 'image' => 'Image',
                'video' => 'Video', 'document' => 'Document', 'email' => 'Email', 'html' => 'HTML',
            ])->required()->default('banner')->disabled($imported),
            Textarea::make('description')->disabled($imported),
            SpatieMediaLibraryFileUpload::make('asset')->collection('creative_asset')
                ->label('Upload file')->hidden($imported)
                ->maxSize((int) ceil((int) config('media-library.max_file_size', 10485760) / 1024)),
            TextInput::make('destination_url')->url()->maxLength(2048)->disabled($imported),
            TextInput::make('width')->integer()->minValue(1)->maxValue(65535)->disabled($imported),
            TextInput::make('height')->integer()->minValue(1)->maxValue(65535)->disabled($imported),
            Textarea::make('html_code')->label('Template')->disabled($imported),
            Toggle::make('is_active')->default(true),
            TextInput::make('sort_order')->integer()->minValue(0)->default(0)->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            // Authorized network administration intentionally spans owners;
            // the relationship still pins every row to this offer.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withoutGlobalScope(ScopesByBelongsToOwner::class)->with('media'))
            ->defaultSort('sort_order')->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('source')->badge()
                    ->state(fn (AffiliateOfferCreative $record): string => $record->external_creative_id !== null ? 'Imported' : 'Manual'),
                TextColumn::make('source_program')->label('Source program')
                    ->state(fn (AffiliateOfferCreative $record): ?string => $record->external_creative_id !== null
                        ? (string) $this->getOwnerRecord()->getAttribute('external_program_id') : null),
                TextColumn::make('asset')->state(fn (AffiliateOfferCreative $record): ?string => $record->getAssetUrl())
                    ->url(fn (?string $state): ?string => $state, shouldOpenInNewTab: true)->limit(40),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('sort_order')->sortable(),
            ])
            ->headerActions([
                CreateAction::make()->using(fn (array $data): AffiliateOfferCreative => $this->saveCreative($data)),
            ])
            ->recordActions([
                EditAction::make()->using(fn (AffiliateOfferCreative $record, array $data): AffiliateOfferCreative => $this->saveCreative($data, $record)),
                DeleteAction::make()->visible(fn (AffiliateOfferCreative $record): bool => $record->external_creative_id === null)
                    ->using(fn (AffiliateOfferCreative $record): bool => $this->deleteCreative($record)),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveCreative(array $data, ?AffiliateOfferCreative $record = null): AffiliateOfferCreative
    {
        return $this->withOfferOwner(function () use ($data, $record): AffiliateOfferCreative {
            /** @var AffiliateOffer $offer */
            $offer = $this->getOwnerRecord();

            if ($record !== null) {
                $record = $offer->creatives()->whereKey($record->getKey())->firstOrFail();

                return app(UpdateCreative::class)->execute($record, $data);
            }

            return app(CreateCreative::class)->execute($offer, $data);
        });
    }

    private function deleteCreative(AffiliateOfferCreative $record): bool
    {
        return $this->withOfferOwner(function () use ($record): bool {
            /** @var AffiliateOffer $offer */
            $offer = $this->getOwnerRecord();
            $creative = $offer->creatives()->whereKey($record->getKey())->firstOrFail();

            return app(DeleteCreative::class)->execute($creative);
        });
    }

    /**
     * Admin write context: the table lists creatives cross-tenant, while the
     * core actions re-query through the offer's owner scope.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    private function withOfferOwner(callable $callback): mixed
    {
        abort_unless(NetworkAdminAccess::allows(), 403);
        $offerId = $this->getOwnerRecord()->getKey();

        $site = OwnerContext::withOwner(null, function () use ($offerId): AffiliateSite {
            $offer = AffiliateOffer::query()->withoutOwnerScope()->whereKey($offerId)->firstOrFail();

            return AffiliateSite::query()->withoutOwnerScope()->whereKey($offer->site_id)->firstOrFail();
        });

        $owner = OwnerContext::fromTypeAndId($site->owner_type, $site->owner_id);

        return OwnerContext::withOwner($owner, $callback);
    }
}
