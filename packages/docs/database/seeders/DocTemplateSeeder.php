<?php

declare(strict_types=1);

namespace AIArmada\Docs\Database\Seeders;

use AIArmada\Docs\Models\DocTemplate;
use AIArmada\Docs\Support\TemplateBlockRegistry;
use Illuminate\Database\Seeder;

class DocTemplateSeeder extends Seeder
{
    public function run(): void
    {
        DocTemplate::create([
            'name' => 'Default Doc Template',
            'slug' => 'doc-default',
            'description' => 'Clean and professional default online document template',
            'doc_type' => 'invoice',
            'is_default' => true,
            'layout' => TemplateBlockRegistry::defaultLayout(),
            'settings' => [
                'show_logo' => false,
                'primary_color' => '#1f2937',
                'accent_color' => '#3b82f6',
            ],
        ]);
    }
}
