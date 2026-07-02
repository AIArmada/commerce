<?php

declare(strict_types=1);

use AIArmada\Ticketing\Actions\TransferPassToHolderAction;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\PassHolder;

it('transfers a pass to a new holder', function () {
    $pass = Pass::factory()->create();
    $newHolder = PassHolder::factory()->make(['name' => 'Bob']);

    $result = app(TransferPassToHolderAction::class)->handle($pass, $newHolder);

    expect($result->name)->toBe('Bob');
});

it('blocks transfer on used pass', function () {
    $pass = Pass::factory()->create(['status' => 'used']);
    $newHolder = PassHolder::factory()->make();

    expect(fn () => app(TransferPassToHolderAction::class)->handle($pass, $newHolder))
        ->toThrow(RuntimeException::class);
});
