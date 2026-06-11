<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Chip\Unit;

use AIArmada\Chip\Support\ChipOwnerTuple;
use Illuminate\Database\Eloquent\Model;



describe('extractFromPayload', function (): void {
    it('extracts owner tuple from payload', function (): void {
        $result = ChipOwnerTuple::extractFromPayload([
            '__owner_type' => 'user',
            '__owner_id' => 'abc-123',
        ]);

        expect($result)->toBe(['user', 'abc-123']);
    });

    it('returns null when owner tuple is missing', function (): void {
        expect(ChipOwnerTuple::extractFromPayload([]))->toBeNull();
    });

    it('returns null when owner_type is not a string', function (): void {
        expect(ChipOwnerTuple::extractFromPayload([
            '__owner_type' => null,
            '__owner_id' => 'abc-123',
        ]))->toBeNull();
    });

    it('returns null when owner_id is not a string or int', function (): void {
        expect(ChipOwnerTuple::extractFromPayload([
            '__owner_type' => 'user',
            '__owner_id' => null,
        ]))->toBeNull();
    });

    it('handles integer owner_id', function (): void {
        $result = ChipOwnerTuple::extractFromPayload([
            '__owner_type' => 'team',
            '__owner_id' => 42,
        ]);

        expect($result)->toBe(['team', '42']);
    });
});

describe('embedInPayload', function (): void {
    it('embeds owner tuple into payload', function (): void {
        $owner = new class extends Model
        {
            public function getMorphClass(): string
            {
                return 'user';
            }

            public function getKey(): string
            {
                return 'user-789';
            }
        };

        $payload = ChipOwnerTuple::embedInPayload([], $owner);

        expect($payload['__owner_type'])->toBe('user');
        expect($payload['__owner_id'])->toBe('user-789');
    });

    it('preserves existing payload keys', function (): void {
        $owner = new class extends Model
        {
            public function getMorphClass(): string
            {
                return 'team';
            }

            public function getKey(): string
            {
                return 'team-42';
            }
        };

        $payload = ChipOwnerTuple::embedInPayload(['existing' => 'value'], $owner);

        expect($payload['existing'])->toBe('value');
        expect($payload['__owner_type'])->toBe('team');
        expect($payload['__owner_id'])->toBe('team-42');
    });
});
