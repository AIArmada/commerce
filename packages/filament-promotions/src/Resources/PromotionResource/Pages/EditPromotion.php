<?php

declare(strict_types=1);

namespace AIArmada\FilamentPromotions\Resources\PromotionResource\Pages;

use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingEngineInterface;
use AIArmada\FilamentPromotions\Actions\IssuePromotionVouchersAction;
use AIArmada\FilamentPromotions\Resources\PromotionResource;
use AIArmada\Promotions\Actions\DeactivatePromotion;
use AIArmada\Promotions\Actions\DeletePromotion;
use AIArmada\Promotions\Actions\UpdatePromotion;
use AIArmada\Promotions\Models\Promotion;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class EditPromotion extends EditRecord
{
    protected static string $resource = PromotionResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Promotion) {
            throw new InvalidArgumentException('Expected a promotion record.');
        }

        return app(UpdatePromotion::class)->handle($record, $data);
    }

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
            ->authorize(static fn (): bool => FilamentPermission::hasAbility('promotion.update'))
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (Promotion $record): bool => $record->is_active)
            ->action(fn (Promotion $record): Promotion => app(DeactivatePromotion::class)->handle($record));
        $actions[] = DeleteAction::make()->using(fn (Promotion $record): bool => app(DeletePromotion::class)->handle($record));

        return $actions;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
