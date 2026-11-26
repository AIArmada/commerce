<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Compliance\EntryPoints\Filament\Resources\AffiliateSupportTicketResource\Pages;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateAccessGuard;
use AIArmada\Affiliates\Features\Support\Api\SupportTicketApi;
use AIArmada\Affiliates\Features\Support\Models\AffiliateSupportTicket;
use AIArmada\FilamentAffiliates\Features\Compliance\EntryPoints\Filament\Resources\AffiliateSupportTicketResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

final class EditAffiliateSupportTicket extends EditRecord
{
    protected static string $resource = AffiliateSupportTicketResource::class;

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

        $data['affiliate_id'] = $this->resolveOwnedAffiliateId((string) $affiliateId);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        app(SupportTicketApi::class)->update((string) $record->getKey(), (string) $data['affiliate_id'], (string) $data['subject'], (string) $data['category'], (string) $data['priority'], (string) $data['status']);

        return $record->refresh();
    }

    private function resolveOwnedAffiliateId(string $affiliateId): string
    {
        try {
            app(AffiliateAccessGuard::class)->assertCanWrite($affiliateId, spl_object_id((new AffiliateSupportTicket)->getConnection()));

            return $affiliateId;
        } catch (AuthorizationException | InvalidArgumentException | RuntimeException) {
            throw ValidationException::withMessages([
                'data.affiliate_id' => 'The selected affiliate is not accessible in the current owner scope.',
            ]);
        }
    }
}
