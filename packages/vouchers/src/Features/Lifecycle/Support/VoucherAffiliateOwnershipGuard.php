<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle\Support;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateReferenceReader;
use AIArmada\Affiliates\Features\Programs\Api\ProgramReferenceReader;
use Illuminate\Validation\ValidationException;

final class VoucherAffiliateOwnershipGuard
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function sanitize(array $data): array
    {
        $data = self::sanitizeRelation($data, 'affiliate_id', AffiliateReferenceReader::class);
        $data = self::sanitizeRelation($data, 'affiliate_program_id', ProgramReferenceReader::class);

        return $data;
    }

    /**
     * @param  class-string<AffiliateReferenceReader|ProgramReferenceReader>  $readerClass
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function sanitizeRelation(array $data, string $snakeKey, string $readerClass): array
    {
        if (! array_key_exists($snakeKey, $data)) {
            return $data;
        }

        $value = $data[$snakeKey];

        if ($value === null || $value === '') {
            $data[$snakeKey] = null;

            return $data;
        }

        if (! is_string($value) && ! is_int($value)) {
            throw ValidationException::withMessages([
                $snakeKey => self::invalidMessage($snakeKey),
            ]);
        }

        $data[$snakeKey] = self::resolveOwnedId($readerClass, (string) $value, $snakeKey);

        return $data;
    }

    /**
     * @param  class-string<AffiliateReferenceReader|ProgramReferenceReader>  $readerClass
     */
    private static function resolveOwnedId(string $readerClass, string $id, string $field): string
    {
        if (! interface_exists($readerClass)
            || ! app()->bound($readerClass)
            || ! app($readerClass)->exists($id)) {
            throw ValidationException::withMessages([
                $field => self::accessDeniedMessage($field),
            ]);
        }

        return $id;
    }

    private static function accessDeniedMessage(string $field): string
    {
        return 'The selected ' . str_replace('_id', '', $field) . ' is not accessible in the current owner scope.';
    }

    private static function invalidMessage(string $field): string
    {
        return 'The selected ' . str_replace('_id', '', $field) . ' is invalid.';
    }
}
