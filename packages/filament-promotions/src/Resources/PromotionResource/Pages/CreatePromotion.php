<?php

declare(strict_types=1);

namespace AIArmada\FilamentPromotions\Resources\PromotionResource\Pages;

use AIArmada\CommerceSupport\Targeting\Contracts\TargetingEngineInterface;
use AIArmada\FilamentPromotions\Resources\PromotionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

final class CreatePromotion extends CreateRecord
{
    protected static string $resource = PromotionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
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

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
