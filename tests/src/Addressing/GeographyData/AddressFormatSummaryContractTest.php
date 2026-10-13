<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Support\AddressFormatRenderer;

/**
 * @return list<string>
 */
function summaryLeakComponentKeys(mixed $node): array
{
    $keys = [];

    if (is_string($node)) {
        $segments = explode(':', $node);

        if (array_shift($segments) === 'components' && isset($segments[0]) && $segments[0] !== '') {
            return [$segments[0]];
        }

        return [];
    }

    if (is_array($node)) {
        foreach ($node as $key => $value) {
            if (in_array($key, ['lit', 'sep', 'display', 'country'], true)) {
                continue;
            }

            foreach (summaryLeakComponentKeys($value) as $found) {
                $keys[] = $found;
            }
        }
    }

    return $keys;
}

it('never leaks street, postcode, or country values into any summary', function (): void {
    $formats = json_decode(
        (string) file_get_contents(__DIR__ . '/../../../../packages/addressing/resources/data/address-formats.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    $fields = [
        'line1' => 'STREET-ONE-zzz',
        'line2' => 'STREET-TWO-zzz',
        'line3' => 'STREET-THREE-zzz',
        'city' => 'Testville',
        'state' => 'Testshire',
        'postcode' => 'PC-999-zzz',
    ];
    $fieldNames = array_keys($fields);
    // Case-insensitive match: an :upper leak must still trip the guard.
    $secrets = ['street-one-zzz', 'street-two-zzz', 'street-three-zzz', 'pc-999-zzz', 'country-zzz'];

    $failures = [];

    foreach ($formats as $code => $definition) {
        $componentKeys = array_unique(array_merge(
            summaryLeakComponentKeys($definition['lines'] ?? []),
            summaryLeakComponentKeys($definition['summary'] ?? []),
        ));

        $components = [];

        foreach ($componentKeys as $key) {
            $components[$key] = 'Component ' . $key;
        }

        $variants = [
            ['country' => null, 'countryCode' => $code, 'components' => []],
            ['country' => 'COUNTRY-zzz', 'countryCode' => $code, 'components' => []],
            ['country' => null, 'countryCode' => $code, 'components' => $components],
        ];

        foreach ($variants as $variantIndex => $variant) {
            for ($mask = 0; $mask < 64; $mask++) {
                $input = [];

                foreach ($fieldNames as $bit => $field) {
                    if (($mask >> $bit) & 1) {
                        $input[$field] = $fields[$field];
                    }
                }

                if ($variant['components'] !== []) {
                    $input['components'] = $variant['components'];
                }

                if ($variant['country'] !== null) {
                    $input['country'] = $variant['country'];
                }

                $input['countryCode'] = $variant['countryCode'];

                try {
                    $parts = AddressFormatRenderer::summaryParts($code, AddressData::from($input));
                } catch (Throwable $exception) {
                    $failures[] = "{$code} variant {$variantIndex} mask {$mask}: threw " . get_class($exception) . ": {$exception->getMessage()}";

                    continue;
                }

                foreach ($parts as $part) {
                    if ($part === '' || $part !== mb_trim($part)) {
                        $failures[] = "{$code} variant {$variantIndex} mask {$mask}: untrimmed part [{$part}].";

                        continue;
                    }

                    $folded = mb_strtolower($part);

                    foreach ($secrets as $secret) {
                        if (str_contains($folded, $secret)) {
                            $failures[] = "{$code} variant {$variantIndex} mask {$mask}: part [{$part}] leaks [{$secret}].";
                        }
                    }
                }

                if (count($failures) > 50) {
                    break 3;
                }
            }
        }
    }

    expect($failures)->toBe([]);
});
