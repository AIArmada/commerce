<?php

declare(strict_types=1);

namespace AIArmada\Chip\Tests\Feature;

use AIArmada\Chip\Contracts\ChipCustomerDirectoryInterface;
use AIArmada\Chip\Listeners\LinkChipCustomerFromCheckoutCompletion;
use AIArmada\Chip\Models\ChipCustomerLink;
use AIArmada\Chip\Support\ChipCustomerBridge;
use AIArmada\Chip\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\Payment\CustomerInterface;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

uses(TestCase::class);

test('skips when event has no session', function (): void {
    $directory = new class implements ChipCustomerDirectoryInterface
    {
        public function findForSubject(Model $subject): ?ChipCustomerLink
        {
            return null;
        }

        public function findByChipCustomerId(string $chipCustomerId, ?Model $owner = null): ?ChipCustomerLink
        {
            return null;
        }

        public function getChipCustomerId(Model $subject): ?string
        {
            return null;
        }

        public function hasChipCustomerId(Model $subject): bool
        {
            return false;
        }

        public function link(Model $subject, string $chipCustomerId, array $metadata = []): ChipCustomerLink
        {
            throw new RuntimeException('Should not be called');
        }

        public function ensureForSubject(Model $subject, CustomerInterface $customer): ChipCustomerLink
        {
            throw new RuntimeException('Should not be called');
        }

        public function syncForSubject(Model $subject, CustomerInterface $customer): ChipCustomerLink
        {
            throw new RuntimeException('Should not be called');
        }
    };

    $bridge = new ChipCustomerBridge($directory);
    $listener = new LinkChipCustomerFromCheckoutCompletion($bridge);
    $listener->handle((object) ['session' => null]);

    expect(true)->toBeTrue();
});
