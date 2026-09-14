<?php

declare(strict_types=1);

use AIArmada\FilamentTicketing\Resources\PassHolderResource;
use AIArmada\FilamentTicketing\Resources\PassResource;
use AIArmada\FilamentTicketing\Resources\PassTransferResource;
use AIArmada\FilamentTicketing\Resources\TicketTypeResource;
use AIArmada\FilamentTicketing\Support\TicketMoney;

it('keeps ticket prices in integer minor units end to end', function (): void {
    expect(TicketMoney::toMinor('10.50'))->toBe(1050)
        ->and(TicketMoney::toMinor('10.505'))->toBe(1051)
        ->and(TicketMoney::toMinor('0.01'))->toBe(1)
        ->and(TicketMoney::toMinor(null))->toBeNull()
        ->and(TicketMoney::toMinor(''))->toBeNull()
        ->and(TicketMoney::toMinor('bogus'))->toBeNull()
        ->and(TicketMoney::toDisplay(1050))->toBe('10.50')
        ->and(TicketMoney::toDisplay(5))->toBe('0.05')
        ->and(TicketMoney::toDisplay(null))->toBeNull();
});

it('detects inverted purchase quantity bounds', function (): void {
    expect(TicketTypeResource::minQuantityExceedsMax(5, 2))->toBeTrue()
        ->and(TicketTypeResource::minQuantityExceedsMax(2, 5))->toBeFalse()
        ->and(TicketTypeResource::minQuantityExceedsMax(2, 2))->toBeFalse()
        ->and(TicketTypeResource::minQuantityExceedsMax(null, 2))->toBeFalse()
        ->and(TicketTypeResource::minQuantityExceedsMax(2, null))->toBeFalse();
});

it('keeps ticketing resources behind permissions', function (): void {
    expect(TicketTypeResource::canViewAny())->toBeFalse()
        ->and(TicketTypeResource::canCreate())->toBeFalse()
        ->and(TicketTypeResource::shouldRegisterNavigation())->toBeFalse()
        ->and(PassResource::canViewAny())->toBeFalse()
        ->and(PassResource::shouldRegisterNavigation())->toBeFalse()
        ->and(PassHolderResource::canViewAny())->toBeFalse()
        ->and(PassHolderResource::shouldRegisterNavigation())->toBeFalse()
        ->and(PassTransferResource::canViewAny())->toBeFalse()
        ->and(PassTransferResource::shouldRegisterNavigation())->toBeFalse();
});
