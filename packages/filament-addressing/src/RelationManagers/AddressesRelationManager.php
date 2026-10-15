<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\RelationManagers;

use AIArmada\Addressing\Actions\UpdateAddressAttachmentAction;
use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\Addressable;
use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\LikeSearch;
use AIArmada\FilamentAddressing\Resources\AddressResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

class AddressesRelationManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

    protected static string $relationship = 'addressAttachments';

    protected static ?string $title = 'Addresses';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('address.label')
            ->columns([
                TextColumn::make('label')->label('Attachment label')->searchable(),
                TextColumn::make('address.line1')->searchable()->limit(40),
                TextColumn::make('address.city')->searchable(),
                TextColumn::make('address.state')->searchable(),
                TextColumn::make('address.postcode'),
                TextColumn::make('address.country_code')->label('Country'),
                TextColumn::make('type')->label('Type')->badge(),
                IconColumn::make('is_primary')->label('Primary')->boolean(),
                TextColumn::make('valid_from')->label('Valid from')->dateTime()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('valid_until')->label('Valid until')->dateTime()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->headerActions([
                Action::make('attachAddress')
                    ->label('Add Address')
                    ->form([
                        Select::make('address_id')
                            ->label('Address')
                            ->getSearchResultsUsing(function (string $search): array {
                                $query = AddressResource::getEloquentQuery();
                                $needle = LikeSearch::contains($search);

                                return $query->where(function (Builder $query) use ($needle): void {
                                    LikeSearch::whereLike($query, 'line1', $needle);
                                    LikeSearch::orWhereLike($query, 'city', $needle);
                                    LikeSearch::orWhereLike($query, 'formatted_address', $needle);
                                })
                                    ->orderBy('line1')
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn (Address $address): array => [
                                        (string) $address->getKey() => $address->formatted_address ?: (string) $address->line1,
                                    ])->all();
                            })
                            ->getOptionLabelUsing(function (mixed $value): ?string {
                                if (! is_string($value)) {
                                    return null;
                                }

                                $address = AddressResource::getEloquentQuery()->whereKey($value)->first();

                                return $address instanceof Address ? ($address->formatted_address ?: (string) $address->line1) : null;
                            })
                            ->searchable()
                            ->required(),
                        TextInput::make('type')->required()->default('primary')->maxLength(100),
                        TextInput::make('label')->maxLength(255),
                        Toggle::make('is_primary'),
                    ])
                    ->visible(fn (): bool => $this->canAttachAddress())
                    ->action(function (array $data): void {
                        $this->assertCanAttach();

                        $address = AddressResource::getEloquentQuery()->whereKey($data['address_id'] ?? null)->firstOrFail();
                        abort_unless(AddressResource::canView($address), 403);
                        $owner = $this->getOwnerRecord();

                        if (! method_exists($owner, 'attachAddress')) {
                            throw new LogicException('The relation owner must support address attachments.');
                        }

                        $owner->attachAddress($address, $data['type'], (bool) ($data['is_primary'] ?? false), $data['label'] ?? null);
                    }),
            ])
            ->recordActions([
                Action::make('editAttachment')
                    ->label('Edit attachment')
                    ->form(fn (Addressable $record): array => [
                        TextInput::make('type')->required()->maxLength(100)->default($record->type),
                        TextInput::make('label')->maxLength(255)->default($record->label),
                        Toggle::make('is_primary')->default((bool) $record->is_primary),
                    ])
                    ->visible(fn (Addressable $record): bool => $this->canMutateAddress($record))
                    ->action(function (Addressable $record, array $data): void {
                        $this->assertCanMutateAddress($record);

                        app(UpdateAddressAttachmentAction::class)->execute($record, [
                            'type' => (string) ($data['type'] ?? ''),
                            'label' => isset($data['label']) ? (string) $data['label'] : null,
                            'is_primary' => (bool) ($data['is_primary'] ?? false),
                        ]);
                    }),
                Action::make('detachAddress')
                    ->label('Remove')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (Addressable $record): bool => $this->canMutateAddress($record))
                    ->action(function (Addressable $record): void {
                        $this->assertCanMutateAddress($record);
                        app(UpdateAddressAttachmentAction::class)->detach($record);
                    }),
            ]);
    }

    private function assertCanAttach(): void
    {
        abort_unless($this->canAttachAddress(), 403);
    }

    private function canAttachAddress(): bool
    {
        return ! AddressResource::isReadOnly()
            && AddressResource::canViewAny()
            && $this->canEditOwner();
    }

    private function canMutateAddress(Addressable $record): bool
    {
        $address = $record->address;

        return ! AddressResource::isReadOnly()
            && $address instanceof Address
            && AddressResource::canView($address)
            && OwnerUiScope::canMutateRecord($address)
            && $this->canEditOwner();
    }

    private function assertCanMutateAddress(Addressable $record): void
    {
        abort_unless($this->canMutateAddress($record), 403);
    }

    private function canEditOwner(): bool
    {
        $pageClass = $this->getPageClass();

        if (! is_subclass_of($pageClass, ResourcePage::class)) {
            return false;
        }

        $resource = $pageClass::getResource();

        return $resource::canEdit($this->getOwnerRecord());
    }
}
