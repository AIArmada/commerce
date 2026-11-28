<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Enums\ProductVisibility;
use AIArmada\Products\Models\Product;
use Closure;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class ImportCatalogProducts
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  Closure(?Product): bool  $authorize
     * @return array{int, int, list<string>}
     */
    public function execute(array $rows, bool $updateExisting, bool $skipErrors, Closure $authorize): array
    {
        $owner = OwnerContext::resolve();

        if (config('products.features.owner.enabled', false)) {
            OwnerContext::assertResolvedOrExplicitGlobal($owner);
        }

        $run = static function () use ($rows, $updateExisting, $skipErrors, $authorize, $owner): array {
            $imported = 0;
            $updated = 0;
            $errors = [];

            foreach ($rows as $offset => $record) {
                try {
                    $wasUpdate = DB::transaction(static function () use ($record, $updateExisting, $authorize, $owner): bool {
                        $existing = null;

                        if ($updateExisting && isset($record['sku']) && mb_trim((string) $record['sku']) !== '') {
                            $existing = Product::query()->forOwner($owner, false)
                                ->where('sku', mb_trim((string) $record['sku']))
                                ->lockForUpdate()->first();
                        }

                        if (! $authorize($existing)) {
                            throw new AuthorizationException('Not authorized to import this product.');
                        }

                        $data = self::validateImportRow($record, $existing instanceof Product);

                        if ($existing instanceof Product) {
                            $existing->update($data);

                            return true;
                        }

                        $product = new Product($data);

                        if ($owner !== null) {
                            $product->assignOwner($owner);
                        }

                        $product->save();

                        return false;
                    });

                    if ($wasUpdate) {
                        $updated++;
                    } else {
                        $imported++;
                    }
                } catch (QueryException $exception) {
                    report($exception);

                    $message = "Row {$offset}: The product could not be saved.";

                    if (! $skipErrors) {
                        throw new RuntimeException($message, previous: $exception);
                    }

                    $errors[] = $message;
                } catch (Exception $exception) {
                    if (! $skipErrors) {
                        throw $exception;
                    }

                    $errors[] = "Row {$offset}: {$exception->getMessage()}";
                }
            }

            return [$imported, $updated, $errors];
        };

        return $skipErrors ? $run() : DB::transaction($run);
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    public static function validateImportRow(array $record, bool $isUpdate): array
    {
        $present = static fn (string $key): bool => isset($record[$key]) && mb_trim((string) $record[$key]) !== '';
        $text = static fn (string $key): string => mb_trim((string) ($record[$key] ?? ''));
        $data = [];

        if (! $isUpdate || $present('name')) {
            $name = $text('name');

            if ($name === '') {
                throw new Exception('The name field is required.');
            }

            if (mb_strlen($name) > 255) {
                throw new Exception('The name may not be longer than 255 characters.');
            }

            $data['name'] = $name;
        }

        if ($present('sku')) {
            if (mb_strlen($text('sku')) > 255) {
                throw new Exception('The sku may not be longer than 255 characters.');
            }

            $data['sku'] = $text('sku');
        } elseif (! $isUpdate && isset($record['sku'])) {
            $data['sku'] = null;
        }

        if (! $isUpdate || $present('slug')) {
            $slug = $present('slug') ? Str::slug($text('slug')) : Str::slug($text('name'));
            $slugMaxLength = (int) config('products.seo.slug_max_length', 100);

            if ($slug === '') {
                throw new Exception('The slug field is required.');
            }

            if (mb_strlen($slug) > $slugMaxLength) {
                throw new Exception("The slug may not be longer than {$slugMaxLength} characters.");
            }

            $data['slug'] = $slug;
        }

        foreach (['description', 'short_description', 'tax_class'] as $key) {
            if ($present($key)) {
                $value = $text($key);

                if ($key === 'tax_class' && mb_strlen($value) > 255) {
                    throw new Exception('The tax class may not be longer than 255 characters.');
                }

                $data[$key] = $value;
            }
        }

        if (! $isUpdate || $present('price')) {
            $data['price'] = self::importMinorAmount($record, 'price', true);
        }

        foreach (['compare_price', 'cost'] as $key) {
            if ($present($key)) {
                $data[$key] = self::importMinorAmount($record, $key, false);
            }
        }

        if ($present('weight')) {
            $weight = $text('weight');

            if (! is_numeric($weight)) {
                throw new Exception('The weight must be numeric.');
            }

            if (! is_finite((float) $weight) || (float) $weight < 0 || (float) $weight > 99999999.99) {
                throw new Exception('The weight must be between 0 and 99999999.99.');
            }

            $data['weight'] = $weight;
        }

        if ($present('currency')) {
            $currency = mb_strtoupper($text('currency'));

            if (! preg_match('/^[A-Z]{3}$/', $currency)) {
                throw new Exception('The currency must be a three-letter code.');
            }

            $data['currency'] = $currency;
        }

        $enumDefaults = [
            'status' => ProductStatus::Draft,
            'type' => ProductType::Simple,
            'visibility' => ProductVisibility::CatalogSearch,
        ];

        foreach (['status' => ProductStatus::class, 'type' => ProductType::class, 'visibility' => ProductVisibility::class] as $key => $enum) {
            if ($present($key)) {
                $value = $enum::tryFrom($text($key));

                if ($value === null) {
                    throw new Exception("The {$key} value is invalid.");
                }

                $data[$key] = $value;
            } elseif (! $isUpdate) {
                $data[$key] = $enumDefaults[$key];
            }
        }

        foreach (['is_featured' => false, 'is_taxable' => true, 'requires_shipping' => true] as $key => $default) {
            if (array_key_exists($key, $record) && mb_trim((string) $record[$key]) !== '') {
                $value = filter_var($record[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                if ($value === null) {
                    throw new Exception("The {$key} value is invalid.");
                }

                $data[$key] = $value;
            } elseif (! $isUpdate) {
                $data[$key] = $default;
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private static function importMinorAmount(array $record, string $key, bool $required): int
    {
        $raw = isset($record[$key]) ? mb_trim((string) $record[$key]) : '';

        if ($raw === '') {
            if ($required) {
                throw new Exception("The {$key} field is required.");
            }

            return 0;
        }

        if (preg_match('/^\+?(?:(\d+)(?:\.(\d*))?|\.(\d+))$/D', $raw, $matches) !== 1) {
            throw new Exception("The {$key} must be a positive number.");
        }

        $whole = $matches[1] ?? '';
        $fraction = $matches[3] ?? ($matches[2] ?? '');
        $digits = ltrim($whole . str_pad(substr($fraction, 0, 2), 2, '0'), '0');
        $digits = $digits === '' ? '0' : $digits;
        $limit = (string) PHP_INT_MAX;

        if (strlen($digits) > strlen($limit)
            || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0)) {
            throw new Exception("The {$key} must be a positive number.");
        }

        $minor = (int) $digits;

        if (isset($fraction[2]) && $fraction[2] >= '5') {
            if ($minor === PHP_INT_MAX) {
                throw new Exception("The {$key} must be a positive number.");
            }

            $minor++;
        }

        return $minor;
    }
}
