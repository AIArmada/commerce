<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\Statements\Api;

interface AccountOperations
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function balance(array $filters = []): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function turnover(array $filters = []): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function companyStatements(array $filters = []): array;

    /**
     * @return array<string, mixed>
     */
    public function companyStatement(string $statementId): array;

    /**
     * @param  array<string, mixed>  $statement
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function scheduleCompanyStatement(array $statement, array $filters = []): array;

    /**
     * @return array<string, mixed>
     */
    public function cancelCompanyStatement(string $statementId): array;
}
