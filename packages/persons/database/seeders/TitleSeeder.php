<?php

declare(strict_types=1);

namespace AIArmada\Persons\Database\Seeders;

use AIArmada\Persons\Enums\TitleUsagePosition;
use AIArmada\Persons\Models\Title;
use AIArmada\Persons\Models\TitleCategory;
use Illuminate\Database\Seeder;

final class TitleSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedLeadingPreNominals();
        $this->seedTrailingPreNominals();
        $this->seedPostNominals();
    }

    private function seedLeadingPreNominals(): void
    {
        $academic = TitleCategory::where('code', 'academic')->first();
        if (! $academic) {
            return;
        }

        Title::firstOrCreate(
            ['category_id' => $academic->id, 'name' => 'Prof'],
            ['short_form' => 'Prof', 'usage_position' => TitleUsagePosition::BeforeName, 'sort_order' => 10],
        );
    }

    private function seedTrailingPreNominals(): void
    {
        $academic = TitleCategory::where('code', 'academic')->first();
        $professional = TitleCategory::where('code', 'professional')->first();

        if ($academic) {
            Title::firstOrCreate(
                ['category_id' => $academic->id, 'name' => 'Dr'],
                ['short_form' => 'Dr', 'usage_position' => TitleUsagePosition::BeforeName, 'sort_order' => 100],
            );
        }

        if ($professional) {
            Title::firstOrCreate(
                ['category_id' => $professional->id, 'name' => 'Ir'],
                ['short_form' => 'Ir', 'usage_position' => TitleUsagePosition::BeforeName, 'sort_order' => 110],
            );
            Title::firstOrCreate(
                ['category_id' => $professional->id, 'name' => 'Ar'],
                ['short_form' => 'Ar', 'usage_position' => TitleUsagePosition::BeforeName, 'sort_order' => 111],
            );
        }
    }

    private function seedPostNominals(): void
    {
        $academic = TitleCategory::where('code', 'academic')->first();
        if (! $academic) {
            return;
        }

        $postNominals = ['PhD', 'MSc', 'MA', 'BSc', 'BA'];

        foreach ($postNominals as $i => $name) {
            Title::firstOrCreate(
                ['category_id' => $academic->id, 'name' => $name],
                [
                    'short_form' => $name,
                    'usage_position' => TitleUsagePosition::AfterName,
                    'sort_order' => ($i + 1) * 10,
                ],
            );
        }
    }
}
