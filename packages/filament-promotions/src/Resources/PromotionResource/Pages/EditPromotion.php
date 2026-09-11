<?php

declare(strict_types=1);

namespace AIArmada\FilamentPromotions\Resources\PromotionResource\Pages;

use AIArmada\CommerceSupport\Targeting\Contracts\TargetingEngineInterface;
use AIArmada\FilamentPromotions\Actions\IssuePromotionVouchersAction;
use AIArmada\FilamentPromotions\Resources\PromotionResource;
use AIArmada\Promotions\Actions\DeactivatePromotion;
use AIArmada\Promotions\Models\Promotion;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

final class EditPromotion extends EditRecord
{
    protected static string $resource = PromotionResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $conditions = $data['conditions'] ?? null;

        if ($conditions === [] || $conditions === null) {
            $data['conditions'] = null;

            return $data;
        }

        if (! is_array($conditions)) {
            throw ValidationException::withMessages([
                'conditions' => 'Conditions must be a key/value map or left empty.',
            ]);
        }

        /** @var TargetingEngineInterface $targetingEngine */
        $targetingEngine = app(TargetingEngineInterface::class);
        $errors = $targetingEngine->validate($conditions);

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'conditions' => 'Invalid targeting conditions: ' . implode(' | ', $errors),
            ]);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        if (Promotion::supportsIssuedVoucherTracking()) {
            $actions[] = IssuePromotionVouchersAction::make();
        }

        $actions[] = ViewAction::make();
        $actions[] = Action::make('deactivate')
            ->label('Deactivate')
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (Promotion $record): bool => $record->is_active)
            ->action(fn (Promotion $record): Promotion => app(DeactivatePromotion::class)->handle($record));
        $actions[] = DeleteAction::make();

        return $actions;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
