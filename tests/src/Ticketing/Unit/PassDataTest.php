<?php

declare(strict_types=1);

use AIArmada\Ticketing\Data\PassData;
use Carbon\CarbonImmutable;

it('creates a PassData DTO with default values', function () {
    $data = new PassData(pass_no: 'PASS-ABC123');

    expect($data->pass_no)->toBe('PASS-ABC123')
        ->and($data->qr_code)->toBeNull()
        ->and($data->barcode)->toBeNull()
        ->and($data->status)->toBe('pending')
        ->and($data->issued_at)->toBeNull()
        ->and($data->holder)->toBeNull();
});

it('creates a PassData DTO with named arguments', function () {
    $data = new PassData(
        pass_no: 'PASS-XYZ789',
        qr_code: 'qr-abc-123',
        barcode: 'bc-456',
        status: 'issued',
    );

    expect($data->pass_no)->toBe('PASS-XYZ789')
        ->and($data->qr_code)->toBe('qr-abc-123')
        ->and($data->barcode)->toBe('bc-456')
        ->and($data->status)->toBe('issued');
});

it('creates a PassData DTO from array', function () {
    $data = PassData::from([
        'pass_no' => 'PASS-FROM-ARRAY',
        'qr_code' => 'qr-from-array',
        'status' => 'activated',
        'issued_at' => '2026-06-01T12:00:00Z',
    ]);

    expect($data->pass_no)->toBe('PASS-FROM-ARRAY')
        ->and($data->qr_code)->toBe('qr-from-array')
        ->and($data->status)->toBe('activated')
        ->and($data->issued_at)->toBeInstanceOf(CarbonImmutable::class);
});
