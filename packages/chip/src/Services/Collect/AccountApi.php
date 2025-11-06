<?php

declare(strict_types=1);

namespace AIArmada\Chip\Services\Collect;

final class AccountApi extends CollectApi
{
    /**
     * @return array<string, mixed>
     */
    public function balance(): array
    {
        return $this->attempt(
            fn () => $this->client->get('account/balance/'),
            'Failed to get CHIP account balance'
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function turnover(array $filters = []): array
    {
        $queryString = http_build_query($filters);
        $endpoint = 'account/turnover/'.($queryString ? '?'.$queryString : '');

        return $this->attempt(
            fn () => $this->client->get($endpoint),
            'Failed to get CHIP account turnover',
            ['filters' => $filters]
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function companyStatements(array $filters = []): array
    {
        $queryString = http_build_query($filters);
        $endpoint = 'company_statements/'.($queryString ? '?'.$queryString : '');

        return $this->attempt(
            fn () => $this->client->get($endpoint),
            'Failed to get CHIP company statements',
            ['filters' => $filters]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function companyStatement(string $statementId): array
    {
        return $this->attempt(
            fn () => $this->client->get("company_statements/{$statementId}/"),
            'Failed to get CHIP company statement',
            ['statement_id' => $statementId]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelCompanyStatement(string $statementId): array
    {
        return $this->attempt(
            fn () => $this->client->post("company_statements/{$statementId}/cancel/"),
            'Failed to cancel CHIP company statement',
            ['statement_id' => $statementId]
        );
    }
}
