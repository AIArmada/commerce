<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateResource\Pages;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\FilamentAffiliates\Actions\ResolveAffiliateLinkedOwner;
use AIArmada\FilamentAffiliates\Actions\ValidateAffiliateParentAssignment;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class EditAffiliate extends EditRecord
{
    protected static string $resource = AffiliateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->authorize(fn (Affiliate $record): bool => AffiliateResource::canDelete($record))
                ->visible(fn (Affiliate $record): bool => AffiliateResource::canDelete($record)),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = parent::mutateFormDataBeforeSave($data);

        $record = $this->getRecord();

        if ($record instanceof Affiliate && ! $record->isCommercialPurpose()) {
            unset(
                $data['name'],
                $data['handle'],
                $data['apply_template'],
                $data['commission_type'],
                $data['commission_rate'],
                $data['currency'],
                $data['network_depth'],
                $data['rank_id'],
                $data['parent_affiliate_id'],
                $data['default_voucher_code'],
                $data['tracking_domain'],
                $data['payout_terms'],
                $data['code'],
                $data['purpose'],
                $data['metadata'],
                $data['owner_type'],
                $data['owner_id'],
                $data['linked_user'],
            );
        }

        if (! $record instanceof Affiliate || $record->isCommercialPurpose()) {
            $data = ValidateAffiliateParentAssignment::run($data, $record instanceof Affiliate ? $record : null);
        }

        return ResolveAffiliateLinkedOwner::run(
            $data,
            $record instanceof Affiliate ? $record : null,
        );
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $ownerType = $data['owner_type'] ?? null;
        $ownerId = $data['owner_id'] ?? null;

        unset($data['owner_type'], $data['owner_id']);

        if (is_string($ownerType) && $ownerType !== '' && $ownerId !== null && $ownerId !== '') {
            $record->forceFill([
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
            ]);
        }

        try {
            $record->update($data);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'data.linked_user' => $exception->getMessage(),
            ]);
        }

        return $record;
    }
}
