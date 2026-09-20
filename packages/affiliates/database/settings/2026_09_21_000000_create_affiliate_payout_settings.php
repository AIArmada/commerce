<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('affiliate-payouts.minimumAmount', 5000);
        $this->migrator->add('affiliate-payouts.minimumAmountsByCurrency', []);
    }
};
