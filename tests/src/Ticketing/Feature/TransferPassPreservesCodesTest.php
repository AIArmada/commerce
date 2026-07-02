<?php

declare(strict_types=1);

use AIArmada\Ticketing\Actions\TransferPassToHolderAction;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\PassHolder;

it('preserves pass_no, qr_code, and barcode after transfer', function () {
    $pass = Pass::factory()->create();
    $originalPassNo = $pass->pass_no;
    $originalQr = $pass->qr_code;
    $originalBarcode = $pass->barcode;

    $pass->refresh();

    app(TransferPassToHolderAction::class)->handle(
        $pass,
        PassHolder::factory()->make()
    );

    $pass->refresh();

    expect($pass->pass_no)->toBe($originalPassNo)
        ->and($pass->qr_code)->toBe($originalQr)
        ->and($pass->barcode)->toBe($originalBarcode);
});
