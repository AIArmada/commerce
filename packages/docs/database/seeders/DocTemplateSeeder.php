<?php

declare(strict_types=1);

namespace AIArmada\Docs\Database\Seeders;

use AIArmada\Docs\Models\DocTemplate;
use Illuminate\Database\Seeder;

class DocTemplateSeeder extends Seeder
{
    public function run(): void
    {
        DocTemplate::create([
            'name' => 'Default Doc Template',
            'slug' => 'doc-default',
            'description' => 'Clean and professional default doc template with Tailwind CSS',
            'view_name' => 'doc-default',
            'doc_type' => 'invoice',
            'is_default' => true,
            'settings' => [
                'show_logo' => false,
                'primary_color' => '#1f2937',
                'accent_color' => '#3b82f6',
            ],
        ]);
    }
}
