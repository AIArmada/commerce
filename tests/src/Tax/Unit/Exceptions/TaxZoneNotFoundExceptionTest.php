<?php

declare(strict_types=1);

namespace AIArmada\Tax\Tests\Unit\Exceptions;

use AIArmada\Commerce\Tests\Tax\TaxTestCase;
use AIArmada\Tax\Exceptions\TaxZoneNotFoundException;
use Exception;

class TaxZoneNotFoundExceptionTest extends TaxTestCase
{
    public function test_exception_creation_with_default_message(): void
    {
        $exception = new TaxZoneNotFoundException();

        $this->assertEquals('Tax zone not found', $exception->getMessage());
    }

    public function test_exception_creation_with_custom_message(): void
    {
        $customMessage = 'Unable to determine tax zone for the given address';
        $exception = new TaxZoneNotFoundException($customMessage);

        $this->assertEquals($customMessage, $exception->getMessage());
    }

    public function test_exception_is_instance_of_exception(): void
    {
        $exception = new TaxZoneNotFoundException();

        $this->assertInstanceOf(Exception::class, $exception);
        $this->assertInstanceOf(TaxZoneNotFoundException::class, $exception);
    }
}
