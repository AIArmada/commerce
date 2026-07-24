<?php

declare(strict_types=1);

namespace AIArmada\Persons\Database\Seeders;

use AIArmada\Persons\Actions\SeedLanguagesAction;
use Illuminate\Database\Seeder;

final class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        app(SeedLanguagesAction::class)->execute();
    }
}
