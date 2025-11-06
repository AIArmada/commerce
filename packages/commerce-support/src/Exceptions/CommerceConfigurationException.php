<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Exceptions;

use Throwable;

/**
 * Exception for configuration errors across commerce packages.
 *
 * This exception is thrown when package configuration is invalid, missing,
 * or incompatible with the current environment.
 */
class CommerceConfigurationException extends CommerceException
{
    /**
     * Create a new configuration exception.
     *
     * @param  string  $message  The exception message
     * @param  string|null  $configKey  The configuration key that caused the error
     * @param  mixed  $configValue  The invalid configuration value
     * @param  string|null  $errorCode  Optional error code
     * @param  Throwable|null  $previous  The previous throwable used for exception chaining
     */
    public function __construct(
        string $message,
        protected ?string $configKey = null,
        protected mixed $configValue = null,
        ?string $errorCode = 'configuration_error',
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $message,
            $errorCode,
            [
                'config_key' => $configKey,
                'config_value' => $configValue,
            ],
            0,
            $previous
        );
    }

    /**
     * Create an exception for a missing configuration.
     */
    public static function missing(string $configKey): static
    {
        return new static( // @phpstan-ignore new.static
            message: "Missing required configuration: {$configKey}",
            configKey: $configKey,
            errorCode: 'config_missing'
        );
    }

    /**
     * Create an exception for an invalid configuration value.
     */
    public static function invalid(string $configKey, mixed $value, string $reason): static
    {
        return new static( // @phpstan-ignore new.static
            message: "Invalid configuration for '{$configKey}': {$reason}",
            configKey: $configKey,
            configValue: $value,
            errorCode: 'config_invalid'
        );
    }

    /**
     * Create an exception for a configuration validation failure.
     *
     * @param  array<string, mixed>  $errors
     */
    public static function validationFailed(array $errors): static
    {
        $keys = implode(', ', array_keys($errors));

        /** @phpstan-ignore-next-line - new.static is safe in static factory methods */
        return new static(
            message: "Configuration validation failed for: {$keys}",
            errorCode: 'config_validation_failed'
        );
    }

    /**
     * Get the configuration key that caused the error.
     */
    public function getConfigKey(): ?string
    {
        return $this->configKey;
    }

    /**
     * Get the invalid configuration value.
     */
    public function getConfigValue(): mixed
    {
        return $this->configValue;
    }

    /**
     * {@inheritDoc}
     */
    public function getContext(): array
    {
        return array_merge(parent::getContext(), [
            'config_key' => $this->configKey,
            'config_value' => $this->configValue,
        ]);
    }
}
