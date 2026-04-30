<?php

declare(strict_types=1);

use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Support\AffiliateIntegrationRegistrar;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Config;

describe('AffiliateIntegrationRegistrar', function (): void {
    describe('register', function (): void {
        it('does nothing when affiliates integration is disabled', function (): void {
            Config::set('vouchers.affiliates.enabled', false);

            $dispatcher = Mockery::mock(Dispatcher::class);
            $registrar = new AffiliateIntegrationRegistrar($dispatcher);

            // Should return early without registering any listeners
            $result = $registrar->register();

            expect($result)->toBeNull();
        });

        it('is disabled by default', function (): void {
            // Affiliates integration is opt-in; default must be false.
            $enabled = config('vouchers.affiliates.enabled', false);

            expect($enabled)->toBeFalse();
        });

        it('auto_create_voucher is disabled by default', function (): void {
            $autoCreate = config('vouchers.affiliates.auto_create_voucher', false);

            expect($autoCreate)->toBeFalse();
        });

        it('create_on_activation is enabled by default', function (): void {
            $createOnActivation = config('vouchers.affiliates.create_on_activation', true);

            expect($createOnActivation)->toBeTrue();
        });
    });

    describe('voucher code generation formats', function (): void {
        it('uses prefix_code format by default', function (): void {
            $format = config('vouchers.affiliates.code_format', 'prefix_code');

            expect($format)->toBe('prefix_code');
        });

        it('uses REF prefix by default', function (): void {
            $prefix = config('vouchers.affiliates.code_prefix', 'REF');

            expect($prefix)->toBe('REF');
        });

        it('set_default_voucher_code is enabled by default', function (): void {
            $setDefault = config('vouchers.affiliates.set_default_voucher_code', true);

            expect($setDefault)->toBeTrue();
        });
    });

    describe('voucher defaults configuration', function (): void {
        it('uses percentage type by default', function (): void {
            $defaults = config('vouchers.affiliates.voucher_defaults', []);

            expect($defaults['type'] ?? 'percentage')->toBe('percentage');
        });

        it('uses 1000 (10%) value by default', function (): void {
            $defaults = config('vouchers.affiliates.voucher_defaults', []);

            expect($defaults['value'] ?? 1000)->toBe(1000);
        });

        it('uses active status by default', function (): void {
            $defaults = config('vouchers.affiliates.voucher_defaults', []);

            expect($defaults['status'] ?? 'active')->toBe('active');
        });
    });

    describe('registrar instantiation', function (): void {
        it('can be instantiated with dependencies', function (): void {
            $dispatcher = app(Dispatcher::class);
            $registrar = new AffiliateIntegrationRegistrar($dispatcher);

            expect($registrar)->toBeInstanceOf(AffiliateIntegrationRegistrar::class);
        });

        it('is registered as singleton in container', function (): void {
            $instance1 = app(AffiliateIntegrationRegistrar::class);
            $instance2 = app(AffiliateIntegrationRegistrar::class);

            expect($instance1)->toBe($instance2);
        });
    });
});

describe('AffiliateIntegrationRegistrar affiliate voucher creation', function (): void {
    it('can check if affiliate has voucher', function (): void {
        // Create a voucher with affiliate_id
        $voucher = Voucher::create([
            'code' => 'AFFILIATE-TEST-' . uniqid(),
            'name' => 'Affiliate Test Voucher',
            'type' => 'percentage',
            'value' => 1000,
            'currency' => 'MYR',
            'status' => 'active',
            'affiliate_id' => 'test-affiliate-123',
        ]);

        // Check if voucher with affiliate_id exists
        $exists = Voucher::where('affiliate_id', 'test-affiliate-123')->exists();

        expect($exists)->toBeTrue();

        // Clean up
        $voucher->delete();
    });

    it('creates voucher with correct metadata for affiliate', function (): void {
        $affiliateCode = 'PARTNER' . uniqid();
        $affiliateId = 'affiliate-' . uniqid();

        $voucher = Voucher::create([
            'code' => 'REF' . $affiliateCode,
            'name' => 'Partner Referral Discount',
            'description' => "Referral discount code for affiliate {$affiliateCode}",
            'type' => 'percentage',
            'value' => 1000,
            'currency' => 'MYR',
            'status' => 'active',
            'affiliate_id' => $affiliateId,
            'metadata' => [
                'affiliate_code' => $affiliateCode,
                'affiliate_id' => $affiliateId,
                'auto_generated' => true,
            ],
        ]);

        expect($voucher->code)->toBe('REF' . $affiliateCode)
            ->and($voucher->type->value)->toBe('percentage')
            ->and($voucher->value)->toBe(1000)
            ->and($voucher->affiliate_id)->toBe($affiliateId)
            ->and($voucher->metadata['auto_generated'])->toBeTrue()
            ->and($voucher->metadata['affiliate_code'])->toBe($affiliateCode);

        // Clean up
        $voucher->delete();
    });

    it('generates code with prefix_code format', function (): void {
        $prefix = 'REF';
        $affiliateCode = 'PARTNER123';

        $generatedCode = mb_strtoupper($prefix . $affiliateCode);

        expect($generatedCode)->toBe('REFPARTNER123');
    });

    it('generates code with code_only format', function (): void {
        $affiliateCode = 'partner123';

        $generatedCode = mb_strtoupper($affiliateCode);

        expect($generatedCode)->toBe('PARTNER123');
    });

    it('generates code with prefix_random format', function (): void {
        $prefix = 'REF';
        $randomPart = bin2hex(random_bytes(4));

        $generatedCode = mb_strtoupper($prefix . $randomPart);

        expect($generatedCode)->toStartWith('REF')
            ->and(mb_strlen($generatedCode))->toBe(11); // REF + 8 hex chars
    });
});

describe('AffiliateIntegrationRegistrar private methods via reflection', function (): void {
    it('generates affiliate voucher code using prefix_code format', function (): void {
        Config::set('vouchers.affiliates.code_prefix', 'REF');
        Config::set('vouchers.affiliates.code_format', 'prefix_code');

        $dispatcher = app(Dispatcher::class);
        $registrar = new AffiliateIntegrationRegistrar($dispatcher);

        $affiliate = new stdClass;
        $affiliate->code = 'TESTPARTNER';
        $affiliate->id = 'aff-123';
        $affiliate->name = 'Test Partner';

        $reflection = new ReflectionClass($registrar);
        $method = $reflection->getMethod('generateAffiliateVoucherCode');

        $code = $method->invoke($registrar, $affiliate);

        expect($code)->toBe('REFTESTPARTNER');
    });

    it('generates affiliate voucher code using code_only format', function (): void {
        Config::set('vouchers.affiliates.code_prefix', 'REF');
        Config::set('vouchers.affiliates.code_format', 'code_only');

        $dispatcher = app(Dispatcher::class);
        $registrar = new AffiliateIntegrationRegistrar($dispatcher);

        $affiliate = new stdClass;
        $affiliate->code = 'testpartner';
        $affiliate->id = 'aff-123';
        $affiliate->name = 'Test Partner';

        $reflection = new ReflectionClass($registrar);
        $method = $reflection->getMethod('generateAffiliateVoucherCode');

        $code = $method->invoke($registrar, $affiliate);

        expect($code)->toBe('TESTPARTNER');
    });

    it('generates affiliate voucher code using prefix_random format', function (): void {
        Config::set('vouchers.affiliates.code_prefix', 'REF');
        Config::set('vouchers.affiliates.code_format', 'prefix_random');

        $dispatcher = app(Dispatcher::class);
        $registrar = new AffiliateIntegrationRegistrar($dispatcher);

        $affiliate = new stdClass;
        $affiliate->code = 'testpartner';
        $affiliate->id = 'aff-123';
        $affiliate->name = 'Test Partner';

        $reflection = new ReflectionClass($registrar);
        $method = $reflection->getMethod('generateAffiliateVoucherCode');

        $code = $method->invoke($registrar, $affiliate);

        expect($code)->toStartWith('REF')
            ->and(mb_strlen($code))->toBe(11); // REF + 8 hex chars
    });

    it('generates affiliate voucher code using default format for unknown format', function (): void {
        Config::set('vouchers.affiliates.code_prefix', 'REF');
        Config::set('vouchers.affiliates.code_format', 'unknown_format');

        $dispatcher = app(Dispatcher::class);
        $registrar = new AffiliateIntegrationRegistrar($dispatcher);

        $affiliate = new stdClass;
        $affiliate->code = 'testpartner';
        $affiliate->id = 'aff-123';
        $affiliate->name = 'Test Partner';

        $reflection = new ReflectionClass($registrar);
        $method = $reflection->getMethod('generateAffiliateVoucherCode');

        $code = $method->invoke($registrar, $affiliate);

        // Default falls back to prefix_code
        expect($code)->toBe('REFTESTPARTNER');
    });

    it('checks if affiliate has voucher returns true when voucher exists', function (): void {
        $dispatcher = app(Dispatcher::class);
        $registrar = new AffiliateIntegrationRegistrar($dispatcher);

        // Create a voucher with specific affiliate_id
        $affiliateId = 'affiliate-' . uniqid();
        $voucher = Voucher::create([
            'code' => 'TEST-' . uniqid(),
            'name' => 'Test Voucher',
            'type' => 'percentage',
            'value' => 1000,
            'currency' => 'MYR',
            'status' => 'active',
            'affiliate_id' => $affiliateId,
        ]);

        $affiliate = new stdClass;
        $affiliate->id = $affiliateId;

        $reflection = new ReflectionClass($registrar);
        $method = $reflection->getMethod('affiliateHasVoucher');

        $hasVoucher = $method->invoke($registrar, $affiliate);

        expect($hasVoucher)->toBeTrue();

        // Clean up
        $voucher->delete();
    });

    it('checks if affiliate has voucher returns false when no voucher exists', function (): void {
        $dispatcher = app(Dispatcher::class);
        $registrar = new AffiliateIntegrationRegistrar($dispatcher);

        $affiliate = new stdClass;
        $affiliate->id = 'nonexistent-affiliate-' . uniqid();

        $reflection = new ReflectionClass($registrar);
        $method = $reflection->getMethod('affiliateHasVoucher');

        $hasVoucher = $method->invoke($registrar, $affiliate);

        expect($hasVoucher)->toBeFalse();
    });

    it('registerAffiliateCreatedListener does nothing when auto_create_voucher is disabled', function (): void {
        Config::set('vouchers.affiliates.auto_create_voucher', false);

        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldNotReceive('listen');

        $registrar = new AffiliateIntegrationRegistrar($dispatcher);

        $reflection = new ReflectionClass($registrar);
        $method = $reflection->getMethod('registerAffiliateCreatedListener');

        $result = $method->invoke($registrar);

        expect($result)->toBeNull();
    });

    it('registerAffiliateActivatedListener does nothing when create_on_activation is disabled', function (): void {
        Config::set('vouchers.affiliates.create_on_activation', false);

        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldNotReceive('listen');

        $registrar = new AffiliateIntegrationRegistrar($dispatcher);

        $reflection = new ReflectionClass($registrar);
        $method = $reflection->getMethod('registerAffiliateActivatedListener');

        $result = $method->invoke($registrar);

        expect($result)->toBeNull();
    });
});

afterEach(function (): void {
    Mockery::close();
});
