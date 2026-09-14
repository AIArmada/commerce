<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\ConditionResource\Pages;

use AIArmada\Cart\Models\Condition;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\FilamentCart\Jobs\RemoveConditionFromAllCartsJob;
use AIArmada\FilamentCart\Resources\ConditionResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

final class EditCondition extends EditRecord
{
    protected static string $resource = ConditionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('removeFromAllCarts')
                ->label('Remove from All Carts')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Remove Condition from All Carts')
                ->modalDescription('This will immediately remove this condition from all active carts. This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, Remove from All Carts')
                ->visible(fn (Condition $record) => $record->is_global && ConditionResource::canEdit($record))
                ->action(function (Condition $record): void {
                    $record = self::authorizeCondition($record);

                    dispatch(RemoveConditionFromAllCartsJob::forCondition($record));

                    Notification::make()
                        ->title('Removal Queued')
                        ->body('The condition is being removed from all carts in the background.')
                        ->success()
                        ->send();
                }),

            Actions\DeleteAction::make()
                ->visible(fn (Condition $record) => ConditionResource::canDelete($record))
                ->using(function (Condition $record): void {
                    self::authorizeCondition($record)->delete();
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['rules'] = Condition::normalizeRulesDefinition(
            $data['rules'] ?? null,
            ! empty($data['rules']['factory_keys'] ?? [])
        );

        return $data;
    }

    private static function authorizeCondition(Condition $condition): Condition
    {
        if (! Condition::ownerScopingEnabled()) {
            return $condition;
        }

        /** @var Condition $validated */
        $validated = OwnerWriteGuard::findOrFailForOwner(
            Condition::class,
            (string) $condition->getKey(),
            includeGlobal: false,
            message: 'Condition is not accessible in the current owner scope.',
        );

        return $validated;
    }
}
