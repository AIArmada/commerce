---
title: Filament Referrals Installation
---

# Filament Referrals Installation

Install the adapter and its core package:

    composer require aiarmada/filament-referrals

Register the plugin on your Filament panel:

    <?php

    use AIArmada\FilamentReferrals\FilamentReferralsPlugin;
    use Filament\Panel;

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->plugin(FilamentReferralsPlugin::make());
    }

Publish the navigation config when you want to change the group or resource ordering:

    php artisan vendor:publish --tag=filament-referrals-config

The core referrals package must be installed and configured with an owner resolver when owner scoping is enabled. See the [referrals installation guide](../../referrals/docs/02-installation.md).
