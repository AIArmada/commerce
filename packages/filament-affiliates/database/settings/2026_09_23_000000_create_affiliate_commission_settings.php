<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('affiliate-commissions.multi_level_enabled', true);
        $this->migrator->add('affiliate-commissions.multi_level_rates', [0.1, 0.05]);
    }
};
