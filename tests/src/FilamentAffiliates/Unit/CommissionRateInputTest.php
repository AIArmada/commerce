<?php

declare(strict_types=1);

use AIArmada\FilamentAffiliates\Features\Identity\EntryPoints\Filament\Resources\AffiliateResource\Schemas\CommissionRateInput;
use Illuminate\Validation\ValidationException;

it('round trips stored commission rates through the numeric form without changing units', function (int $stored, string $type, string $currency, string $display): void {
    expect(CommissionRateInput::format($stored, $type, $currency))->toBe($display)
        ->and(CommissionRateInput::parse($display, $type, $currency))->toBe($stored);
})->with([
    [1000, 'fixed', 'MYR', '10.00'],
    [123456, 'fixed', 'USD', '1234.56'],
    [1000, 'fixed', 'JPY', '1000'],
    [125, 'percentage', 'JPY', '1.25'],
    [0, 'fixed', 'MYR', '0.00'],
]);

it('rejects invalid or unrepresentable commission rates', function (string $rate): void {
    expect(fn () => CommissionRateInput::parse($rate, 'fixed', 'MYR'))->toThrow(ValidationException::class);
})->with(['-1', '0.001', '1e3', '1,000', '999999999999999999999999']);

it('preserves nullable commission rates and parses fractional input exactly', function (): void {
    expect(CommissionRateInput::format(null, 'fixed', 'MYR'))->toBeNull()
        ->and(CommissionRateInput::parse('', 'fixed', 'MYR'))->toBeNull()
        ->and(CommissionRateInput::parse('.25', 'fixed', 'MYR'))->toBe(25);
});
