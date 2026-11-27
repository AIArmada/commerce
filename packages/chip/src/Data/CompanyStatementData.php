<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

use Carbon\CarbonImmutable;

final readonly class CompanyStatementData
{
    public function __construct(
        public string $id,
        public string $type,
        public string $format,
        public string $timezone,
        public bool $is_test,
        public string $company_uid,
        public ?string $query_string,
        public string $status,
        public ?string $download_url,
        public ?int $began_on,
        public ?int $finished_on,
        public int $created_on,
        public int $updated_on,
    ) {}

    /** @param array<string, mixed> $data */
    public static function from(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            type: (string) ($data['type'] ?? 'statement'),
            format: (string) ($data['format'] ?? ''),
            timezone: (string) ($data['timezone'] ?? 'UTC'),
            is_test: (bool) ($data['is_test'] ?? false),
            company_uid: (string) ($data['company_uid'] ?? ''),
            query_string: isset($data['query_string']) ? (string) $data['query_string'] : null,
            status: (string) ($data['status'] ?? ''),
            download_url: isset($data['download_url']) ? (string) $data['download_url'] : null,
            began_on: isset($data['began_on']) ? (int) $data['began_on'] : null,
            finished_on: isset($data['finished_on']) ? (int) $data['finished_on'] : null,
            created_on: (int) ($data['created_on'] ?? time()),
            updated_on: (int) ($data['updated_on'] ?? time()),
        );
    }

    public function getBeganAt(): ?CarbonImmutable
    {
        return $this->began_on ? CarbonImmutable::createFromTimestamp($this->began_on) : null;
    }

    public function getFinishedAt(): ?CarbonImmutable
    {
        return $this->finished_on ? CarbonImmutable::createFromTimestamp($this->finished_on) : null;
    }

    public function getCreatedAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestamp($this->created_on);
    }

    public function getUpdatedAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestamp($this->updated_on);
    }

    public function isReady(): bool
    {
        return $this->status === 'finished';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'format' => $this->format,
            'timezone' => $this->timezone,
            'is_test' => $this->is_test,
            'company_uid' => $this->company_uid,
            'query_string' => $this->query_string,
            'status' => $this->status,
            'download_url' => $this->download_url,
            'began_on' => $this->began_on,
            'finished_on' => $this->finished_on,
            'created_on' => $this->created_on,
            'updated_on' => $this->updated_on,
        ];
    }
}
