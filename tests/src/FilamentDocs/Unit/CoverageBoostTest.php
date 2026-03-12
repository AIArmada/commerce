<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentDocs\Actions\RecordPaymentAction;
use AIArmada\FilamentDocs\Actions\SendEmailAction;
use AIArmada\FilamentDocs\Exports\DocExporter;
use AIArmada\FilamentDocs\Pages\AgingReportPage;
use AIArmada\FilamentDocs\Pages\PendingApprovalsPage;
use AIArmada\FilamentDocs\Resources\DocEmailTemplateResource;
use AIArmada\FilamentDocs\Resources\DocResource;
use AIArmada\FilamentDocs\Resources\DocResource\Pages\EditDoc;
use AIArmada\FilamentDocs\Resources\DocResource\Pages\ListDocs;
use AIArmada\FilamentDocs\Resources\DocResource\Pages\ViewDoc;
use AIArmada\FilamentDocs\Resources\DocResource\RelationManagers\ApprovalsRelationManager;
use AIArmada\FilamentDocs\Resources\DocResource\RelationManagers\EmailsRelationManager;
use AIArmada\FilamentDocs\Resources\DocResource\RelationManagers\PaymentsRelationManager;
use AIArmada\FilamentDocs\Resources\DocResource\RelationManagers\StatusHistoriesRelationManager;
use AIArmada\FilamentDocs\Resources\DocResource\RelationManagers\VersionsRelationManager;
use AIArmada\FilamentDocs\Resources\DocResource\Schemas\DocForm;
use AIArmada\FilamentDocs\Resources\DocResource\Schemas\DocInfolist;
use AIArmada\FilamentDocs\Resources\DocResource\Tables\DocsTable;
use AIArmada\FilamentDocs\Resources\DocSequenceResource;
use AIArmada\FilamentDocs\Resources\DocTemplateResource;
use AIArmada\FilamentDocs\Resources\DocTemplateResource\Pages\EditDocTemplate;
use AIArmada\FilamentDocs\Resources\DocTemplateResource\Pages\ListDocTemplates;
use AIArmada\FilamentDocs\Resources\DocTemplateResource\Pages\ViewDocTemplate;
use AIArmada\FilamentDocs\Resources\DocTemplateResource\Schemas\DocTemplateForm;
use AIArmada\FilamentDocs\Resources\DocTemplateResource\Schemas\DocTemplateInfolist;
use AIArmada\FilamentDocs\Resources\DocTemplateResource\Tables\DocTemplatesTable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component as LivewireComponent;

uses(TestCase::class);

afterEach(function (): void {
    Mockery::close();
});

if (! function_exists('filamentDocs_makeSchemaLivewire')) {
    function filamentDocs_makeSchemaLivewire(): LivewireComponent & HasSchemas
    {
        return new class extends LivewireComponent implements HasSchemas
        {
            public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
            {
                return null;
            }

            public function getOldSchemaState(string $statePath): mixed
            {
                return null;
            }

            public function getSchemaComponent(
                string $key,
                bool $withHidden = false,
                array $skipComponentsChildContainersWhileSearching = [],
            ): Component | Action | ActionGroup | null {
                return null;
            }

            public function getSchema(string $name): ?Schema
            {
                return null;
            }

            public function currentlyValidatingSchema(?Schema $schema): void {}

            public function getDefaultTestingSchemaName(): ?string
            {
                return null;
            }
        };
    }
}

function makeFilamentDocsTable(): Table
{
    /** @var HasTable $livewire */
    $livewire = Mockery::mock(HasTable::class);

    return Table::make($livewire);
}

it('builds FilamentDocs resources, schemas, tables, relation managers, pages, and actions', function (): void {
    $schemaLivewire = filamentDocs_makeSchemaLivewire();

    foreach ([
        DocResource::class,
        DocTemplateResource::class,
        DocSequenceResource::class,
        DocEmailTemplateResource::class,
    ] as $resource) {
        expect($resource::form(Schema::make($schemaLivewire)))->toBeInstanceOf(Schema::class);

        if (method_exists($resource, 'infolist')) {
            expect($resource::infolist(Schema::make($schemaLivewire)))->toBeInstanceOf(Schema::class);
        }

        expect($resource::table(makeFilamentDocsTable()))->toBeInstanceOf(Table::class);
        expect($resource::getPages())->toBeArray();
    }

    // Schema/table builders
    expect(DocForm::configure(Schema::make($schemaLivewire)))->toBeInstanceOf(Schema::class);
    expect(DocInfolist::configure(Schema::make($schemaLivewire)))->toBeInstanceOf(Schema::class);
    expect(DocsTable::configure(makeFilamentDocsTable()))->toBeInstanceOf(Table::class);

    expect(DocTemplateForm::configure(Schema::make($schemaLivewire)))->toBeInstanceOf(Schema::class);
    expect(DocTemplateInfolist::configure(Schema::make($schemaLivewire)))->toBeInstanceOf(Schema::class);
    expect(DocTemplatesTable::configure(makeFilamentDocsTable()))->toBeInstanceOf(Table::class);

    // Relation managers
    foreach ([
        ApprovalsRelationManager::class,
        EmailsRelationManager::class,
        PaymentsRelationManager::class,
        StatusHistoriesRelationManager::class,
        VersionsRelationManager::class,
    ] as $manager) {
        $instance = app($manager);

        if (method_exists($instance, 'form')) {
            expect($instance->form(Schema::make($schemaLivewire)))->toBeInstanceOf(Schema::class);
        }

        expect($instance->table(makeFilamentDocsTable()))->toBeInstanceOf(Table::class);
    }

    // Resource pages: invoke protected header action builders via reflection.
    foreach ([
        ListDocs::class,
        EditDoc::class,
        ViewDoc::class,
        ListDocTemplates::class,
        EditDocTemplate::class,
        ViewDocTemplate::class,
    ] as $pageClass) {
        $page = app($pageClass);

        $method = new ReflectionMethod($pageClass, 'getHeaderActions');
        $method->setAccessible(true);

        expect($method->invoke($page))->toBeArray();
    }

    // Standalone pages
    foreach ([AgingReportPage::class, PendingApprovalsPage::class] as $pageClass) {
        $page = app($pageClass);

        if (method_exists($page, 'getTitle')) {
            $page->getTitle();
        }

        $page->table(Table::make($page));
    }

    // Actions
    expect(RecordPaymentAction::make())->toBeInstanceOf(Action::class);
    expect(SendEmailAction::make())->toBeInstanceOf(Action::class);
    expect(DocExporter::getColumns())->toBeArray();
});
