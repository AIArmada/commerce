<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Compliance;

use AIArmada\Affiliates\Features\Support\Models\AffiliateSupportTicket;
use AIArmada\FilamentAffiliates\Features\Compliance\EntryPoints\Filament\Policies\AffiliateSupportTicketPolicy;
use AIArmada\Affiliates\Features\Tax\Models\AffiliateTaxDocument;
use AIArmada\FilamentAffiliates\Features\Compliance\EntryPoints\Filament\Policies\AffiliateTaxDocumentPolicy;
use AIArmada\FilamentAffiliates\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {}

    public function boot(Application $app): void
    {
        Gate::policy(AffiliateSupportTicket::class, AffiliateSupportTicketPolicy::class);
        Gate::policy(AffiliateTaxDocument::class, AffiliateTaxDocumentPolicy::class);
    }
}
