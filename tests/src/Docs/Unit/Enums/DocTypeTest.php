<?php

use AIArmada\Docs\Enums\DocType;

test('doc type labels', function () {
    expect(DocType::Invoice->label())->toBe('Invoice');
    expect(DocType::Quotation->label())->toBe('Quotation');
    expect(DocType::CreditNote->label())->toBe('Credit Note');
    expect(DocType::DeliveryNote->label())->toBe('Delivery Note');
    expect(DocType::ProformaInvoice->label())->toBe('Proforma Invoice');
    expect(DocType::Receipt->label())->toBe('Receipt');
});

test('doc type colors', function () {
    expect(DocType::Invoice->color())->toBe('primary');
    expect(DocType::Quotation->color())->toBe('info');
});

test('doc type icons', function () {
    expect(DocType::Invoice->icon())->toBe('heroicon-o-document-text');
});

test('doc type default prefix', function () {
    expect(DocType::Invoice->defaultPrefix())->toBe('INV');
    expect(DocType::Quotation->defaultPrefix())->toBe('QUO');
    expect(DocType::CreditNote->defaultPrefix())->toBe('CN');
});

test('doc type requires payment', function () {
    expect(DocType::Invoice->requiresPayment())->toBeTrue();
    expect(DocType::ProformaInvoice->requiresPayment())->toBeTrue();
    expect(DocType::Quotation->requiresPayment())->toBeFalse();
});

test('doc type conversion', function () {
    expect(DocType::Quotation->canConvertToInvoice())->toBeTrue();
    expect(DocType::ProformaInvoice->canConvertToInvoice())->toBeTrue();
    expect(DocType::Invoice->canConvertToInvoice())->toBeFalse();
});

test('doc type conversion sources', function () {
    expect(DocType::Invoice->getConversionSources())->toContain(DocType::Quotation, DocType::ProformaInvoice);
    expect(DocType::Receipt->getConversionSources())->toContain(DocType::Invoice);
    expect(DocType::Quotation->getConversionSources())->toBeEmpty();
});
