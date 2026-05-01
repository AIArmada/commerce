---
title: Customization
---

# Customization

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

When adding custom actions, keep the package's owner checks in place so tenant isolation stays enforced.

```php
<?php

namespace App\Filament\Resources\DocResource\Pages;

use AIArmada\Docs\Services\DocService;
use AIArmada\FilamentDocs\Resources\DocResource\Pages\ViewDoc as BaseViewDoc;
use AIArmada\FilamentDocs\Support\DocsOwnerScope;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ViewDoc extends BaseViewDoc
{
    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),

            Action::make('duplicate')
                ->icon('heroicon-o-document-duplicate')
                ->action(function (): void {
                    DocsOwnerScope::assertCanMutateDoc($this->record);

                    $newDoc = app(DocService::class)->clone($this->record);

                    Notification::make()
                        ->success()
                        ->title('Document duplicated')
                        ->send();

                    $this->redirect(static::$resource::getUrl('edit', ['record' => $newDoc]));
                }),
        ];
    }
}
```

## Localization

Most labels in the package are plain strings or wrapped in Laravel translation helpers, so you can override them in your own resource/page classes when you need localized labels.
