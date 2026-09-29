<?php

declare(strict_types=1);

namespace AIArmada\Chip\Exceptions;

use Exception;
use Throwable;

class ChipApiException extends Exception
{
    /**
     * @var array<string, mixed>
     */
    protected array $errorData = [];

    /**
     * @param  array<string, mixed>  $errorData
     */
    public function __construct(
        string $message = '',
        protected int $statusCode = 0,
        array $errorData = [],
        ?Throwable $previous = null
    ) {
        $this->errorData = $errorData;
        parent::__construct($message, $statusCode, $previous);
    }

    public static function fromResponse(mixed $responseData, int $statusCode): self
    {
        $responseData = self::normalizeErrorData($responseData);
        $allError = self::extractAllError($responseData);
        $message = $responseData['error'] ?? $responseData['message'] ?? $allError['message'] ?? 'Unknown API error';

        // Extract the error details, excluding the message
        $errorDetails = $responseData;
        unset($errorDetails['error'], $errorDetails['message']);

        if ($allError !== null && $allError['code'] !== null && ! isset($errorDetails['code'])) {
            $errorDetails['code'] = $allError['code'];
        }

        return new self($message, $statusCode, $errorDetails);
    }

    /**
     * Scalar bodies (proxy text, numeric codes) carry no error object. A
     * non-blank string becomes the message; anything else behaves like an
     * empty body. Arrays pass through untouched.
     *
     * @return array<string, mixed>
     */
    public static function normalizeErrorData(mixed $responseData): array
    {
        if (is_array($responseData)) {
            return $responseData;
        }

        if (is_string($responseData) && mb_trim($responseData) !== '') {
            return ['message' => $responseData];
        }

        return [];
    }

    /**
     * Extract CHIP's `__all__` error payload. The spec shows an object
     * (`{"message", "code"}`) at :1269 and a list of such objects at
     * :301/:327; all 11 live errors sampled (2026-09-29 sandbox) carried
     * lists, so the object form is accepted but unconfirmed. Only the
     * first list entry is inspected; later entries are never scanned.
     * A bare string entry (or list item) yields a message-only error.
     * Non-string message/code values coerce to null; a missing `__all__`,
     * or one that is neither array nor string, yields null.
     *
     * @return ?array{code: ?string, message: ?string}
     */
    public static function extractAllError(mixed $responseData): ?array
    {
        if (! is_array($responseData)) {
            return null;
        }

        $all = $responseData['__all__'] ?? null;

        if (is_array($all) && array_is_list($all)) {
            $all = $all[0] ?? null;
        }

        if (is_string($all)) {
            return ['code' => null, 'message' => $all];
        }

        if (! is_array($all)) {
            return null;
        }

        $message = $all['message'] ?? null;
        $code = $all['code'] ?? null;

        return [
            'code' => is_string($code) ? $code : null,
            'message' => is_string($message) ? $message : null,
        ];
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function getErrorData(): array
    {
        return $this->errorData;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorData['code'] ?? null;
    }

    public function getErrorMessage(): string
    {
        return $this->errorData['message'] ?? $this->getMessage();
    }

    /**
     * @return array<string, mixed>
     */
    public function getErrorDetails(): array
    {
        return $this->errorData;
    }

    public function hasErrorDetails(): bool
    {
        return ! empty($this->errorData);
    }

    public function getFormattedMessage(): string
    {
        $message = $this->getMessage();

        if (! empty($this->errorData)) {
            $details = [];
            foreach ($this->errorData as $key => $value) {
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                $details[] = "{$key}: {$value}";
            }

            $message .= ' - ' . implode(', ', $details);
        }

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'status_code' => $this->statusCode,
            'error_data' => $this->errorData,
        ];
    }
}
