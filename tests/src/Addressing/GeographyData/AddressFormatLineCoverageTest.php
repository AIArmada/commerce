<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Support\AddressFormatRenderer;

/**
 * @return list<string>
 */
function formatCoverageSpecs(mixed $node): array
{
    $specs = [];

    if (is_string($node)) {
        return [$node];
    }

    if (is_array($node)) {
        foreach ($node as $key => $value) {
            if ($key === 'lit' || $key === 'sep' || $key === 'display' || $key === 'country') {
                continue;
            }

            foreach (formatCoverageSpecs($value) as $spec) {
                $specs[] = $spec;
            }
        }
    }

    return $specs;
}

/**
 * Value overrides that fire value-sensitive branches (:in: members, !literal
 * suppressions, !dup comparisons, components reads, country chain modes).
 *
 * @return list<array{fields: array<string, string>, components: array<string, string>, country: ?string, countryCode: ?string}>
 */
function formatCoverageVariants(string $code, array $definition): array
{
    $base = [
        'line1' => 'Line One',
        'line2' => 'Line Two',
        'line3' => 'Line Three',
        'city' => 'Testville',
        'state' => 'Testshire',
        'postcode' => '12345',
    ];

    $inMembers = [];
    $literals = [];
    $componentKeys = [];
    $hasDup = false;

    foreach (formatCoverageSpecs($definition['lines']) as $spec) {
        $segments = explode(':', $spec);
        $field = array_shift($segments);

        if ($field === 'components' && isset($segments[0]) && $segments[0] !== '') {
            $componentKeys[$segments[0]] = true;

            continue;
        }

        for ($i = 0; $i < count($segments); $i++) {
            $op = $segments[$i];

            if ($op === 'in' && isset($segments[$i + 1])) {
                $members = array_values(array_filter(
                    array_map(static fn (string $member): string => mb_trim($member), explode(',', $segments[$i + 1])),
                    static fn (string $member): bool => $member !== '',
                ));

                if ($members !== [] && ! isset($inMembers[$field])) {
                    $inMembers[$field] = $members[0];
                }

                $i++;
            } elseif ($op === '!dup') {
                $hasDup = true;
                $i++;
            } elseif (str_starts_with($op, '!') && $op !== '!') {
                $literals[$field] = mb_substr($op, 1);
            }
        }
    }

    $variants = [
        ['fields' => $base, 'components' => [], 'country' => null, 'countryCode' => $code],
    ];

    if ($hasDup) {
        $dup = $base;
        $dup['city'] = 'Springfield';
        $dup['state'] = 'Springfield';
        $variants[] = ['fields' => $dup, 'components' => [], 'country' => null, 'countryCode' => $code];

        $dupCase = $base;
        $dupCase['city'] = 'Springfield';
        $dupCase['state'] = 'SPRINGFIELD';
        $variants[] = ['fields' => $dupCase, 'components' => [], 'country' => null, 'countryCode' => $code];
    }

    if ($inMembers !== []) {
        $variants[] = ['fields' => array_merge($base, $inMembers), 'components' => [], 'country' => null, 'countryCode' => $code];
    }

    if ($literals !== []) {
        $variants[] = ['fields' => array_merge($base, $literals), 'components' => [], 'country' => null, 'countryCode' => $code];
    }

    if ($componentKeys !== []) {
        $all = [];
        foreach (array_keys($componentKeys) as $key) {
            $all[$key] = 'Component ' . $key;
        }
        $variants[] = ['fields' => $base, 'components' => $all, 'country' => null, 'countryCode' => $code];

        foreach (array_keys($componentKeys) as $key) {
            $variants[] = ['fields' => $base, 'components' => [$key => 'Component ' . $key], 'country' => null, 'countryCode' => $code];
        }
    }

    $variants[] = ['fields' => $base, 'components' => [], 'country' => 'Custom Land', 'countryCode' => $code];
    $variants[] = ['fields' => $base, 'components' => [], 'country' => null, 'countryCode' => 'XX'];

    return $variants;
}

it('fires every format line and keeps output pure across the field matrix', function (): void {
    $formats = json_decode(
        (string) file_get_contents(__DIR__ . '/../../../../packages/addressing/resources/data/address-formats.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    $fields = ['line1', 'line2', 'line3', 'city', 'state', 'postcode'];
    $failures = [];

    foreach ($formats as $code => $definition) {
        $fired = [];
        $lineCount = count($definition['lines']);

        foreach (formatCoverageVariants($code, $definition) as $variantIndex => $variant) {
            for ($mask = 0; $mask < 64; $mask++) {
                $input = [];

                foreach ($fields as $bit => $field) {
                    if (($mask >> $bit) & 1) {
                        $input[$field] = $variant['fields'][$field];
                    }
                }

                if ($variant['components'] !== []) {
                    $input['components'] = $variant['components'];
                }

                if ($variant['country'] !== null) {
                    $input['country'] = $variant['country'];
                }

                if ($variant['countryCode'] !== null) {
                    $input['countryCode'] = $variant['countryCode'];
                }

                $trace = null;
                $output = AddressFormatRenderer::format($code, AddressData::from($input), $trace);

                foreach ($trace ?? [] as $index) {
                    $fired[$index] = true;
                }

                if (str_contains($output, "\n\n")) {
                    $failures[] = "{$code} variant {$variantIndex} mask {$mask}: blank line in output.";
                }

                foreach (explode("\n", $output) as $line) {
                    if ($line !== mb_trim($line)) {
                        $failures[] = "{$code} variant {$variantIndex} mask {$mask}: untrimmed line [{$line}].";

                        break;
                    }
                }

                if (count($failures) > 50) {
                    break 3;
                }
            }
        }

        $missing = [];

        for ($index = 0; $index < $lineCount; $index++) {
            if (! isset($fired[$index])) {
                $missing[] = $index;
            }
        }

        if ($missing !== []) {
            $failures[] = "{$code}: lines never fired [" . implode(',', $missing) . '] of ' . $lineCount . '.';
        }
    }

    expect($failures)->toBe([]);
});
