<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Concerns;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Validates Configuration
 *
 * Provides helper methods for validating configuration values
 * at runtime. Used by integration packages (chip, jnt).
 */
/**
 * @phpstan-ignore trait.unused
 */
trait ValidatesConfiguration
{
    /**
     * Require configuration keys to be set, throw exception if missing.
     *
     * @param  string  $prefix  Config prefix (e.g., 'chip', 'jnt')
     * @param  array<string>  $keys  Required keys
     * @param  bool  $throwException  Whether to throw exception or just log warning
     *
     * @throws RuntimeException
     */
    protected function requireConfigKeys(
        string $prefix,
        array $keys,
        bool $throwException = true
    ): void {
        $missing = [];

        foreach ($keys as $key) {
            $value = config("{$prefix}.{$key}");

            if (empty($value)) {
                $missing[] = $key;
            }
        }

        if (! empty($missing)) {
            $message = sprintf(
                '%s: Missing required configuration keys: %s',
                ucfirst($prefix),
                implode(', ', array_map(fn ($k) => "{$prefix}.{$k}", $missing))
            );

            if ($throwException) {
                throw new RuntimeException($message);
            }

            Log::warning($message);
        }
    }

    /**
     * Validate that a configuration value is in a list of allowed values.
     *
     * @param  string  $key  Config key (e.g., 'jnt.environment')
     * @param  array<string>  $allowed  Allowed values
     * @param  bool  $throwException  Whether to throw exception or just log warning
     *
     * @throws RuntimeException
     */
    protected function validateConfigEnum(
        string $key,
        array $allowed,
        bool $throwException = false
    ): void {
        $value = config($key);

        if ($value && ! in_array($value, $allowed, true)) {
            $message = sprintf(
                'Invalid configuration value for %s: %s. Must be one of: %s',
                $key,
                $value,
                implode(', ', $allowed)
            );

            if ($throwException) {
                throw new RuntimeException($message);
            }

            Log::warning($message);
        }
    }

    /**
     * Validate that a configuration value is a valid URL.
     *
     * @param  string  $key  Config key (e.g., 'jnt.base_url')
     * @param  bool  $throwException  Whether to throw exception or just log warning
     *
     * @throws RuntimeException
     */
    protected function validateConfigUrl(
        string $key,
        bool $throwException = false
    ): void {
        $value = config($key);

        if ($value && ! filter_var($value, FILTER_VALIDATE_URL)) {
            $message = sprintf('Invalid URL format for configuration key %s: %s', $key, $value);

            if ($throwException) {
                throw new RuntimeException($message);
            }

            Log::warning($message);
        }
    }
}
