<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\CartResource\Pages;

use AIArmada\FilamentCart\Resources\CartResource;
use AIArmada\FilamentCart\Services\CartInstanceManager;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

final class EditCart extends EditRecord
{
    protected static string $resource = CartResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->icon(Heroicon::OutlinedEye),

            Actions\DeleteAction::make()
                ->icon(Heroicon::OutlinedTrash),

            Actions\Action::make('clear_cart')
                ->label('Clear Cart')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (): void {
                    /** @var \AIArmada\FilamentCart\Models\Cart $record */
                    $record = $this->record;
                    app(CartInstanceManager::class)
                        ->resolve($record->instance, $record->identifier)
                        ->clear();
                    $this->redirect($this->getResource()::getUrl('index'));
                })
                /** @phpstan-ignore-next-line */
                ->visible(fn (): bool => $this->record->items_count > 0),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Ensure items and conditions are arrays
        $data['items'] = $data['items'] ?? [];
        $data['conditions'] = $data['conditions'] ?? [];
        $data['metadata'] = $data['metadata'] ?? [];

        return $data;
    }
}
