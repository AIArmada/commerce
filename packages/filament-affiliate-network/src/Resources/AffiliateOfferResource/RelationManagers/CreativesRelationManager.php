<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers;

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
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

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
                    ->using(fn (AffiliateOfferCreative $record): bool => $this->withOfferOwner(function (AffiliateOffer $offer) use ($record): bool {
                        $creative = $offer->creatives()->whereKey($record->getKey())->firstOrFail();
                        abort_if($creative->external_creative_id !== null, 403);

                        return (bool) $creative->delete();
                    })),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveCreative(array $data, ?AffiliateOfferCreative $record = null): AffiliateOfferCreative
    {
        return $this->withOfferOwner(function (AffiliateOffer $offer) use ($data, $record): AffiliateOfferCreative {
            $creative = $record !== null ? $offer->creatives()->whereKey($record->getKey())->firstOrFail() : null;
            $rules = ['is_active' => ['required', 'boolean'], 'sort_order' => ['required', 'integer', 'min:0']];

            if ($creative?->external_creative_id === null) {
                $rules += [
                    'name' => ['required', 'string', 'max:255'],
                    'type' => ['required', 'in:banner,text,image,video,document,email,html'],
                    'description' => ['nullable', 'string'],
                    'destination_url' => ['nullable', 'url:http,https', 'max:2048'],
                    'width' => ['nullable', 'integer', 'min:1', 'max:65535'],
                    'height' => ['nullable', 'integer', 'min:1', 'max:65535'],
                    'html_code' => ['nullable', 'string'],
                ];
            }

            $validated = Validator::make(Arr::only($data, array_keys($rules)), $rules)->validate();

            if ($creative !== null) {
                $creative->update($validated);

                return $creative;
            }

            return $offer->creatives()->create($validated);
        });
    }

    /**
     * @template TResult
     *
     * @param  callable(AffiliateOffer): TResult  $callback
     * @return TResult
     */
    private function withOfferOwner(callable $callback): mixed
    {
        abort_unless(NetworkAdminAccess::allows(), 403);
        $offerId = $this->getOwnerRecord()->getKey();
        // Re-resolve parent and site on every write; client record IDs are
        // then checked through the scoped parent relationship.
        $site = OwnerContext::withOwner(null, function () use ($offerId): AffiliateSite {
            $offer = AffiliateOffer::query()->withoutOwnerScope()->whereKey($offerId)->firstOrFail();

            return AffiliateSite::query()->withoutOwnerScope()->whereKey($offer->site_id)->firstOrFail();
        });

        return OwnerContext::withOwner($site->owner, function () use ($offerId, $callback): mixed {
            return $callback(AffiliateOffer::query()->whereKey($offerId)->firstOrFail());
        });
    }
}
