<?php

declare(strict_types=1);

use AIArmada\Tax\Exceptions\TaxZoneNotFoundException;

describe('TaxZoneNotFoundException', function (): void {
    it('exception creation with default message', function (): void {
        $exception = new TaxZoneNotFoundException;

        $this->assertEquals('Tax zone not found', $exception->getMessage());
    });

    it('exception creation with custom message', function (): void {
        $customMessage = 'Unable to determine tax zone for the given address';
        $exception = new TaxZoneNotFoundException($customMessage);

        $this->assertEquals($customMessage, $exception->getMessage());
    });

    it('exception is instance of exception', function (): void {
        $exception = new TaxZoneNotFoundException;

        $this->assertInstanceOf(Exception::class, $exception);
        $this->assertInstanceOf(TaxZoneNotFoundException::class, $exception);
    });
});
