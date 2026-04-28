<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerScopeIdentifiable;
use AIArmada\CommerceSupport\Support\OwnerFilesystem;
use AIArmada\CommerceSupport\Support\OwnerScopeKey;
use Illuminate\Support\Facades\Storage;

describe('OwnerFilesystem', function (): void {
    beforeEach(function (): void {
        Storage::fake('local');
    });

    it('builds owner-scoped filesystem paths', function (): void {
        $owner = new class implements OwnerScopeIdentifiable
        {
            public function getMorphClass(): string
            {
                return 'store';
            }

            public function getKey(): mixed
            {
                return 'store-123';
            }
        };

        $path = OwnerFilesystem::path($owner, 'invoices/2025-01.pdf');

        expect($path)->toMatch('/^owners\/[a-f0-9]{64}\/invoices\/2025-01\.pdf$/');
    });

    it('builds global filesystem paths for null owner', function (): void {
        $path = OwnerFilesystem::path(null, 'backups/data.sql');

        expect($path)->toBe('owners/' . OwnerScopeKey::GLOBAL . '/backups/data.sql');
    });

    it('rejects empty relative paths', function (): void {
        expect(fn () => OwnerFilesystem::path(null, ''))
            ->toThrow(InvalidArgumentException::class, 'cannot be empty');
    });

    it('rejects paths with directory traversal', function (): void {
        expect(fn () => OwnerFilesystem::path(null, '../../../etc/passwd'))
            ->toThrow(InvalidArgumentException::class, 'invalid traversal');

        expect(fn () => OwnerFilesystem::path(null, '/etc/passwd'))
            ->toThrow(InvalidArgumentException::class, 'invalid traversal');
    });

    it('stores files for an owner', function (): void {
        $owner = new class implements OwnerScopeIdentifiable
        {
            public function getMorphClass(): string
            {
                return 'store';
            }

            public function getKey(): mixed
            {
                return 'store-put';
            }
        };

        $result = OwnerFilesystem::put($owner, 'test.txt', 'Hello, Owner!');

        expect($result)->toBeTrue();
        expect(Storage::disk()->get(OwnerFilesystem::path($owner, 'test.txt')))->toBe('Hello, Owner!');
    });

    it('retrieves files for an owner', function (): void {
        $owner = new class implements OwnerScopeIdentifiable
        {
            public function getMorphClass(): string
            {
                return 'store';
            }

            public function getKey(): mixed
            {
                return 'store-get';
            }
        };

        OwnerFilesystem::put($owner, 'data.txt', 'Data content');

        $content = OwnerFilesystem::get($owner, 'data.txt');

        expect($content)->toBe('Data content');
    });

    it('returns default when owner file not found', function (): void {
        $owner = new class implements OwnerScopeIdentifiable
        {
            public function getMorphClass(): string
            {
                return 'store';
            }

            public function getKey(): mixed
            {
                return 'store-404';
            }
        };

        $result = OwnerFilesystem::get($owner, 'nonexistent.txt', 'default-content');

        expect($result)->toBe('default-content');
    });

    it('checks if owner file exists', function (): void {
        $owner = new class implements OwnerScopeIdentifiable
        {
            public function getMorphClass(): string
            {
                return 'store';
            }

            public function getKey(): mixed
            {
                return 'store-exists';
            }
        };

        OwnerFilesystem::put($owner, 'exists.txt', 'exists');

        expect(OwnerFilesystem::exists($owner, 'exists.txt'))->toBeTrue()
            ->and(OwnerFilesystem::exists($owner, 'notexists.txt'))->toBeFalse();
    });

    it('deletes owner files', function (): void {
        $owner = new class implements OwnerScopeIdentifiable
        {
            public function getMorphClass(): string
            {
                return 'store';
            }

            public function getKey(): mixed
            {
                return 'store-del';
            }
        };

        OwnerFilesystem::put($owner, 'delete-me.txt', 'content');

        expect(OwnerFilesystem::exists($owner, 'delete-me.txt'))->toBeTrue();

        $result = OwnerFilesystem::delete($owner, 'delete-me.txt');

        expect($result)->toBeTrue()
            ->and(OwnerFilesystem::exists($owner, 'delete-me.txt'))->toBeFalse();
    });

    it('prevents file access across different owners', function (): void {
        $owner1 = new class implements OwnerScopeIdentifiable
        {
            public function getMorphClass(): string
            {
                return 'store';
            }

            public function getKey(): mixed
            {
                return 'store-1';
            }
        };

        $owner2 = new class implements OwnerScopeIdentifiable
        {
            public function getMorphClass(): string
            {
                return 'store';
            }

            public function getKey(): mixed
            {
                return 'store-2';
            }
        };

        OwnerFilesystem::put($owner1, 'secret.txt', 'owner1-data');

        expect(OwnerFilesystem::get($owner2, 'secret.txt'))->toBeNull();
    });

    it('copies files within owner scope', function (): void {
        $owner = new class implements OwnerScopeIdentifiable
        {
            public function getMorphClass(): string
            {
                return 'store';
            }

            public function getKey(): mixed
            {
                return 'store-copy';
            }
        };

        OwnerFilesystem::put($owner, 'original.txt', 'original content');
        $result = OwnerFilesystem::copy($owner, 'original.txt', 'copied.txt');

        expect($result)->toBeTrue()
            ->and(OwnerFilesystem::get($owner, 'copied.txt'))->toBe('original content');
    });

    it('moves files within owner scope', function (): void {
        $owner = new class implements OwnerScopeIdentifiable
        {
            public function getMorphClass(): string
            {
                return 'store';
            }

            public function getKey(): mixed
            {
                return 'store-move';
            }
        };

        OwnerFilesystem::put($owner, 'source.txt', 'source content');
        $result = OwnerFilesystem::move($owner, 'source.txt', 'dest.txt');

        expect($result)->toBeTrue()
            ->and(OwnerFilesystem::exists($owner, 'source.txt'))->toBeFalse()
            ->and(OwnerFilesystem::get($owner, 'dest.txt'))->toBe('source content');
    });

    it('isolates global and owner files', function (): void {
        $owner = new class implements OwnerScopeIdentifiable
        {
            public function getMorphClass(): string
            {
                return 'store';
            }

            public function getKey(): mixed
            {
                return 'store-iso';
            }
        };

        OwnerFilesystem::put(null, 'shared.txt', 'global');
        OwnerFilesystem::put($owner, 'shared.txt', 'owner');

        expect(OwnerFilesystem::get(null, 'shared.txt'))->toBe('global')
            ->and(OwnerFilesystem::get($owner, 'shared.txt'))->toBe('owner');
    });
});
