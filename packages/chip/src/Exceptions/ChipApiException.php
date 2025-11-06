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

    /**
     * @param  array<string, mixed>  $responseData
     */
    public static function fromResponse(array $responseData, int $statusCode): self
    {
        $message = $responseData['error'] ?? $responseData['message'] ?? 'Unknown API error';

        // Extract the error details, excluding the message
        $errorDetails = $responseData;
        unset($errorDetails['error'], $errorDetails['message']);

        return new self($message, $statusCode, $errorDetails);
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

            $message .= ' - '.implode(', ', $details);
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
