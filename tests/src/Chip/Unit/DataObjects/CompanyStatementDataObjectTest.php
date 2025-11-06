<?php

declare(strict_types=1);

use AIArmada\Chip\DataObjects\CompanyStatement;

describe('CompanyStatement data object', function (): void {
    it('normalises statement payload', function (): void {
        $payload = [
            'id' => 'stmt_123',
            'type' => 'statement',
            'format' => 'csv',
            'timezone' => 'Europe/Oslo',
            'is_test' => true,
            'company_uid' => 'company_456',
            'query_string' => 'status=finished',
            'status' => 'finished',
            'download_url' => 'https://example.com/file.csv',
            'began_on' => 1712074800,
            'finished_on' => 1712078400,
            'created_on' => 1712070000,
            'updated_on' => 1712078600,
        ];

        $statement = CompanyStatement::fromArray($payload);

        expect($statement->id)->toBe('stmt_123')
            ->and($statement->format)->toBe('csv')
            ->and($statement->timezone)->toBe('Europe/Oslo')
            ->and($statement->is_test)->toBeTrue()
            ->and($statement->company_uid)->toBe('company_456')
            ->and($statement->query_string)->toBe('status=finished')
            ->and($statement->status)->toBe('finished')
            ->and($statement->download_url)->toBe('https://example.com/file.csv')
            ->and($statement->began_on)->toBe(1712074800)
            ->and($statement->finished_on)->toBe(1712078400)
            ->and($statement->created_on)->toBe(1712070000)
            ->and($statement->updated_on)->toBe(1712078600);

        expect($statement->getBeganAt()?->timestamp)->toBe(1712074800)
            ->and($statement->getFinishedAt()?->timestamp)->toBe(1712078400)
            ->and($statement->getCreatedAt()->timestamp)->toBe(1712070000)
            ->and($statement->getUpdatedAt()->timestamp)->toBe(1712078600);

        expect($statement->isReady())->toBeTrue()
            ->and($statement->isCancelled())->toBeFalse();

        expect($statement->toArray())->toMatchArray([
            'id' => 'stmt_123',
            'status' => 'finished',
            'format' => 'csv',
        ]);
    });
});
