<?php

declare(strict_types=1);

use AIArmada\Addressing\Contracts\CountryAddressFormatter;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Malaysia\MalaysiaAddressFormatter;
use AIArmada\Addressing\Support\CountryAddressFormatterResolver;

final class UninstantiableFakeAddressFormatter implements CountryAddressFormatter
{
    public function __construct()
    {
        throw new RuntimeException('Formatter must not be instantiated during map build.');
    }

    public static function countryCode(): string
    {
        return 'ZZ';
    }

    public function format(AddressData $address): string
    {
        return '';
    }
}

it('resolves a fresh formatter instance per lookup', function (): void {
    $resolver = app(CountryAddressFormatterResolver::class);

    $first = $resolver->resolve('MY');
    $second = $resolver->resolve('my');

    expect($first)->toBeInstanceOf(MalaysiaAddressFormatter::class)
        ->and($second)->toBeInstanceOf(MalaysiaAddressFormatter::class)
        ->and($second)->not->toBe($first);
});

it('returns null for blank and unknown country codes', function (): void {
    $resolver = app(CountryAddressFormatterResolver::class);

    expect($resolver->resolve(null))->toBeNull()
        ->and($resolver->resolve('  '))->toBeNull()
        ->and($resolver->resolve('XX'))->toBeNull();
});

it('builds the code map without instantiating formatters', function (): void {
    config()->set('addressing.formatters', [
        UninstantiableFakeAddressFormatter::class,
        MalaysiaAddressFormatter::class,
    ]);

    $resolver = app(CountryAddressFormatterResolver::class);

    expect($resolver->resolve('MY'))->toBeInstanceOf(MalaysiaAddressFormatter::class);

    expect(fn () => $resolver->resolve('ZZ'))->toThrow(RuntimeException::class);
});
