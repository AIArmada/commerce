<?php

declare(strict_types=1);

use AIArmada\FilamentDocs\Resources\DocResource\Pages\CreateDoc;
use AIArmada\FilamentDocs\Resources\DocResource\Pages\EditDoc;
use AIArmada\FilamentDocs\Resources\DocResource\Pages\ListDocs;
use AIArmada\FilamentDocs\Resources\DocResource\Pages\ViewDoc;

test('list docs page has correct title', function (): void {
    $page = new ListDocs();

    expect($page->getTitle())->toBe('Documents');
    expect($page->getSubheading())->toBe('Manage invoices, receipts, and other documents');
});

test('create doc page can be instantiated', function (): void {
    $page = new CreateDoc();

    expect($page)->toBeInstanceOf(CreateDoc::class);
});

test('edit doc page can be instantiated', function (): void {
    $page = new EditDoc();

    expect($page)->toBeInstanceOf(EditDoc::class);
});

test('view doc page can be instantiated', function (): void {
    $page = new ViewDoc();

    expect($page)->toBeInstanceOf(ViewDoc::class);
});