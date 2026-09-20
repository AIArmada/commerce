<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource\Pages;

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource\Schemas\AffiliateSiteForm;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\CreateRecord;

final class CreateAffiliateSite extends CreateRecord
{
    protected static string $resource = AffiliateSiteResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['domain']) && is_string($data['domain'])) {
            $data['domain'] = mb_strtolower(mb_trim($data['domain']));
        }

        // Keep the verified invariant (status=verified ⟺ verified_at set) when
        // an admin creates a site as already verified.
        if (($data['status'] ?? null) === AffiliateSite::STATUS_VERIFIED && empty($data['verified_at'])) {
            $data['verified_at'] = CarbonImmutable::now();
        }

        return AffiliateSiteForm::mergeCatalogToken($data);
    }
}
