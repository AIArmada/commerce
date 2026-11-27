<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource\RelationManagers;

use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use AIArmada\Shipping\Features\Returns\Models\ReturnAuthorization;
use AIArmada\Shipping\Features\Returns\Models\ReturnAuthorizationItem;
use AIArmada\Shipping\Features\Returns\Api\ManageReturnAuthorization;
use AIArmada\Shipping\Features\Returns\States\RmaDraft;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

    protected static string $relationship = 'items';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('sku')
                    ->label('SKU')
                    ->maxLength(100),

                Forms\Components\TextInput::make('quantity_requested')
                    ->numeric()
                    ->required()
                    ->default(1)
                    ->minValue(1),
            ]);
    }

    public function table(Table $table): Table
    {
        $canUpdateReturn = function (): bool {
            $user = auth()->user();

            if ($user === null) {
                return false;
            }

            $owner = $this->getOwnerRecord();

            return $owner instanceof ReturnAuthorization
                && ! $owner->isPackageReturn()
                && ($owner->isPending() || $owner->status instanceof RmaDraft)
                && $user->can('update', $owner);
        };

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('quantity_requested')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity_received')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('condition')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'unopened' => 'success',
                        'opened' => 'info',
                        'damaged' => 'danger',
                        'defective' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('condition')
                    ->options([
                        'unopened' => 'Unopened',
                        'opened' => 'Opened/Used',
                        'damaged' => 'Damaged',
                        'defective' => 'Defective',
                    ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->authorize($canUpdateReturn)
                    ->using(function (array $data): ReturnAuthorizationItem {
                        $owner = $this->getOwnerRecord();

                        if (! $owner instanceof ReturnAuthorization) {
                            throw new \LogicException('Return authorization is required.');
                        }

                        $id = app(ManageReturnAuthorization::class)->createItem((string) $owner->getKey(), $data);

                        return $owner->items()->findOrFail($id);
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->authorize($canUpdateReturn)
                    ->using(function (ReturnAuthorizationItem $record, array $data): ReturnAuthorizationItem {
                        app(ManageReturnAuthorization::class)->updateItem((string) $this->getOwnerRecord()->getKey(), (string) $record->getKey(), $data);

                        return $record->refresh();
                    }),
                DeleteAction::make()
                    ->authorize($canUpdateReturn)
                    ->using(fn (ReturnAuthorizationItem $record): bool => app(ManageReturnAuthorization::class)->deleteItem((string) $this->getOwnerRecord()->getKey(), (string) $record->getKey())),
            ])
            ->bulkActions([]);
    }
}
