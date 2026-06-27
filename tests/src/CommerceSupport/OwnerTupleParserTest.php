<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Illuminate\Database\Eloquent\Model;

describe('OwnerTupleParser', function (): void {
    it('parses explicit global tuples from row data', function (): void {
        $parsed = OwnerTupleParser::fromRow(
            row: [
                'owner_type' => null,
                'owner_id' => null,
            ],
            columns: new OwnerTupleColumns,
        );

        expect($parsed->isExplicitGlobal())->toBeTrue()
            ->and($parsed->isOwner())->toBeFalse()
            ->and($parsed->isUnresolved())->toBeFalse();
    });

    it('parses owner tuples from row data', function (): void {
        $parsed = OwnerTupleParser::fromRow(
            row: [
                'owner_type' => TestOwnerTupleModel::class,
                'owner_id' => 'owner-1',
            ],
            columns: new OwnerTupleColumns,
        );

        expect($parsed->isOwner())->toBeTrue()
            ->and($parsed->owner_type)->toBe(TestOwnerTupleModel::class)
            ->and((string) $parsed->owner_id)->toBe('owner-1');
    });

    it('returns unresolved tuples for malformed data when malformed tuples are allowed', function (): void {
        $parsed = OwnerTupleParser::fromRow(
            row: [
                'owner_type' => 'users',
                'owner_id' => null,
            ],
            columns: new OwnerTupleColumns,
            allowMalformed: true,
        );

        expect($parsed->isUnresolved())->toBeTrue();
    });

    it('throws for malformed tuple data by default', function (): void {
        expect(fn () => OwnerTupleParser::fromRow(
            row: [
                'owner_type' => 'users',
                'owner_id' => null,
            ],
            columns: new OwnerTupleColumns,
        ))->toThrow(InvalidArgumentException::class);
    });

    it('resolves owner tuple column names from model scope config', function (): void {
        $columns = OwnerTupleColumns::forModelClass(TestOwnerTupleScopedModel::class);

        expect($columns->ownerTypeColumn)->toBe('tenant_type')
            ->and($columns->ownerIdColumn)->toBe('tenant_id');
    });
});

final class TestOwnerTupleModel extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}

final class TestOwnerTupleScopedModel extends Model
{
    use HasOwnerScopeConfig;

    protected static string $ownerScopeConfigKey = 'testing.owner';

    protected static string $ownerScopeOwnerTypeColumn = 'tenant_type';

    protected static string $ownerScopeOwnerIdColumn = 'tenant_id';

    public $timestamps = false;

    protected $guarded = [];
}
