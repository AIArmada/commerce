<?php

declare(strict_types=1);

use AIArmada\Ticketing\Contracts\PassTransferServiceInterface;
use AIArmada\Ticketing\Features\Passes\Actions\TransferPassToHolderAction;
use AIArmada\Ticketing\Features\Passes\Models\Pass;
use AIArmada\Ticketing\Features\Passes\Models\PassHolder;
use Carbon\CarbonImmutable;

it('blocks transfer at and after the deadline without scheduled cleanup', function (): void {
    $deadline = CarbonImmutable::now()->startOfSecond()->addHour();
    $pass = Pass::factory()->create(['transfer_expires_at' => $deadline]);

    $this->travelTo($deadline);

    try {
        expect(app(PassTransferServiceInterface::class)->canTransfer((string) $pass->getKey()))->toBeFalse();

        expect(fn () => app(TransferPassToHolderAction::class)->handle(
            $pass,
            PassHolder::factory()->make(),
        ))->toThrow(RuntimeException::class);

        expect($pass->fresh()->transfer_expires_at->equalTo($deadline))->toBeTrue();
    } finally {
        $this->travelBack();
    }
});

it('allows a valid pass with no configured transfer deadline', function (): void {
    $pass = Pass::factory()->create(['transfer_expires_at' => null]);

    expect(app(PassTransferServiceInterface::class)->canTransfer((string) $pass->getKey()))->toBeTrue();
});
