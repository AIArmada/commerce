<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateLinkResource\Pages;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\FilamentAffiliates\Resources\AffiliateLinkResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateLinkResource\Pages\Concerns\FillsTrackingUrl;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

final class EditAffiliateLink extends EditRecord
{
    use FillsTrackingUrl;

    protected static string $resource = AffiliateLinkResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $affiliateId = $data['affiliate_id'] ?? null;

        if (! is_string($affiliateId) && ! is_int($affiliateId)) {
            throw ValidationException::withMessages([
                'data.affiliate_id' => 'The selected affiliate is invalid.',
            ]);
        }

        $data['affiliate_id'] = $this->resolveOwnedId(
            Affiliate::class,
            (string) $affiliateId,
            'affiliate_id',
        );

        $programId = $data['program_id'] ?? null;

        if ($programId === null || $programId === '') {
            $data['program_id'] = null;

            return $this->fillTrackingUrl($data);
        }

        if (! is_string($programId) && ! is_int($programId)) {
            throw ValidationException::withMessages([
                'data.program_id' => 'The selected program is invalid.',
            ]);
        }

        $data['program_id'] = $this->resolveOwnedId(
            AffiliateProgram::class,
            (string) $programId,
            'program_id',
        );

        return $this->fillTrackingUrl($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     */
    private function resolveOwnedId(string $model, string $id, string $field): string
    {
        if (! (bool) config('affiliates.owner.enabled', false)) {
            $record = $model::query()->find($id);

            if (! $record instanceof Model) {
                throw ValidationException::withMessages([
                    'data.' . $field => 'The selected ' . str_replace('_id', '', $field) . ' is invalid.',
                ]);
            }

            return (string) $record->getKey();
        }

        try {
            return (string) OwnerWriteGuard::findOrFailForOwner(
                $model,
                $id,
                includeGlobal: (bool) config('affiliates.owner.include_global', false),
                message: 'The selected ' . str_replace('_id', '', $field) . ' is not accessible in the current owner scope.',
            )->getKey();
        } catch (AuthorizationException | InvalidArgumentException | RuntimeException) {
            throw ValidationException::withMessages([
                'data.' . $field => 'The selected ' . str_replace('_id', '', $field) . ' is not accessible in the current owner scope.',
            ]);
        }
    }
}
