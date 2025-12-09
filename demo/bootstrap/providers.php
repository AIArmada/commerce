<?php

declare(strict_types=1);
use AIArmada\FilamentChip\BillingPanelProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    BillingPanelProvider::class,
];
