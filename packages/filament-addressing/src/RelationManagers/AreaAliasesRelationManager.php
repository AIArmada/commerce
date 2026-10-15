<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\RelationManagers;

use AIArmada\Addressing\Actions\DeleteAddressAreaAliasAction;
use AIArmada\Addressing\Actions\SaveAddressAreaAliasAction;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaName;
use AIArmada\FilamentAddressing\Resources\AddressAreaResource;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class AreaAliasesRelationManager extends RelationManager
{
    protected static string $relationship = 'names';

    protected static ?string $title = 'Aliases';

    public function isReadOnly(): bool
    {
        return AddressAreaResource::isReadOnly();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('name_type')->label('Type')->badge(),
                TextColumn::make('source')->badge(),
                IconColumn::make('is_preferred')->label('Preferred')->boolean(),
            ])
            ->headerActions([
                Action::make('addAlias')
                    ->label('Add manual alias')
                    ->form(self::formSchema())
                    ->visible(fn (): bool => $this->ownerCanEdit())
                    ->action(function (array $data): void {
                        $owner = $this->getOwnerRecord();

                        if (! $owner instanceof AddressArea) {
                            abort(404);
                        }

                        $this->assertOwnerCanEdit($owner);

                        app(SaveAddressAreaAliasAction::class)->handle($owner, $data);

                        Notification::make()->title('Alias added')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('editAlias')
                    ->label('Edit')
                    ->fillForm(fn (AddressAreaName $record): array => [
                        'name' => $record->name,
                        'name_type' => $record->name_type,
                        'is_preferred' => $record->is_preferred,
                    ])
                    ->form(self::formSchema())
                    ->visible(fn (AddressAreaName $record): bool => $record->source === 'manual' && $this->ownerCanEdit())
                    ->action(function (AddressAreaName $record, array $data): void {
                        $owner = $this->getOwnerRecord();

                        if (! $owner instanceof AddressArea) {
                            abort(404);
                        }

                        $this->assertOwnerCanEdit($owner);

                        app(SaveAddressAreaAliasAction::class)->handle($owner, $data, $record);

                        Notification::make()->title('Alias updated')->success()->send();
                    }),
                Action::make('deleteAlias')
                    ->label('Delete')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (AddressAreaName $record): bool => $record->source === 'manual' && $this->ownerCanEdit())
                    ->action(function (AddressAreaName $record): void {
                        $owner = $this->getOwnerRecord();

                        if (! $owner instanceof AddressArea) {
                            abort(404);
                        }

                        $this->assertOwnerCanEdit($owner);

                        app(DeleteAddressAreaAliasAction::class)->execute($owner, $record);

                        Notification::make()->title('Alias deleted')->success()->send();
                    }),
            ])
            ->defaultSort('name')
            ->paginated([10, 25, 50]);
    }

    /**
     * @return array<int, TextInput|Toggle>
     */
    private static function formSchema(): array
    {
        return [
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('name_type')->required()->default('alternative')->maxLength(32),
            Toggle::make('is_preferred')->label('Preferred'),
        ];
    }

    private function assertOwnerCanEdit(AddressArea $owner): void
    {
        abort_unless(
            ! AddressAreaResource::isReadOnly() && AddressAreaResource::canEdit($owner),
            403,
        );
    }

    private function ownerCanEdit(): bool
    {
        $owner = $this->getOwnerRecord();

        return $owner instanceof AddressArea
            && ! AddressAreaResource::isReadOnly()
            && AddressAreaResource::canEdit($owner);
    }
}
