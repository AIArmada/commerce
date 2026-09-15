<?php

declare(strict_types=1);

use AIArmada\FilamentDocs\Resources\DocResource\Pages\ListDocs;

test('list docs page has correct title', function (): void {
    $page = new ListDocs;

    expect($page->getTitle())->toBe('Documents');
    expect($page->getSubheading())->toBe('Manage invoices, receipts, and other documents');
});

/* Page instantiation tautologies removed; page wiring is covered by CoverageBoostTest. */
