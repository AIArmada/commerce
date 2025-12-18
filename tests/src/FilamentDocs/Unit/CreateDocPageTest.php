<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\Docs\DataObjects\DocData;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Services\DocService;
use AIArmada\FilamentDocs\Resources\DocResource\Pages\CreateDoc;

uses(TestCase::class);

it('delegates document creation to DocService and defaults generate_pdf from config', function (): void {
    config()->set('filament-docs.auto_generate_pdf', true);

    $createdDoc = Doc::factory()->create();

    $service = Mockery::mock(DocService::class);
    $service->shouldReceive('createDoc')
        ->once()
        ->with(Mockery::on(function (DocData $data): bool {
            return $data->docType === 'invoice' && $data->generatePdf === true;
        }))
        ->andReturn($createdDoc);

    app()->instance(DocService::class, $service);

    $page = app(CreateDoc::class);

    $method = new ReflectionMethod(CreateDoc::class, 'handleRecordCreation');
    $method->setAccessible(true);

    $doc = $method->invoke($page, [
        'doc_type' => 'invoice',
        'items' => [],
    ]);

    expect($doc->is($createdDoc))->toBeTrue();
});
