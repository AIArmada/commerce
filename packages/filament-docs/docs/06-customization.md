---
title: Customization
---

# Customization

Use this page after you understand the shipped resources and pages and need to swap or extend them safely.

This package is easiest to customize by swapping resource classes through the plugin and reusing the package's schemas, tables, and actions where helpful.

## Swap Resource Classes

```php
use AIArmada\FilamentDocs\FilamentDocsPlugin;

FilamentDocsPlugin::make()
    ->docResource(\App\Filament\Resources\DocResource::class)
    ->docTemplateResource(\App\Filament\Resources\DocTemplateResource::class)
    ->docSequenceResource(\App\Filament\Resources\DocSequenceResource::class)
    ->docEmailTemplateResource(\App\Filament\Resources\DocEmailTemplateResource::class);
```

## Reuse Package Schemas

```php
<?php

namespace App\Filament\Resources\DocResource\Schemas;

use AIArmada\FilamentDocs\Resources\DocResource\Schemas\DocForm as BaseDocForm;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class DocForm
{
    public static function configure(Schema $schema): Schema
    {
        return BaseDocForm::configure($schema)->schema([
            Select::make('metadata.project_id')
                ->label('Project')
                ->options(fn () => \App\Models\Project::query()->pluck('name', 'id')),
        ]);
    }
}
```

## Reuse Package Tables

```php
<?php

namespace App\Filament\Resources\DocResource\Tables;

use AIArmada\FilamentDocs\Resources\DocResource\Tables\DocsTable as BaseDocsTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocsTable
{
    public static function configure(Table $table): Table
    {
        return BaseDocsTable::configure($table)
            ->columns([
                TextColumn::make('metadata.project_id')
                    ->label('Project'),
            ]);
    }
}
```

## Custom Actions

When adding custom actions, owner scoping is automatic via `HasOwner` on the `Doc` model — the `OwnerScope` global scope handles queries and model hooks guard mutations. No manual owner checks are needed.

## Localization

Most labels in the package are plain strings or wrapped in Laravel translation helpers, so you can override them in your own resource/page classes when you need localized labels.
