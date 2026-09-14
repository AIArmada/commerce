<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Actions\UpsertEnvVariablesAction;
use Illuminate\Filesystem\Filesystem;

function withIsolatedEnvBasePath(string $envContent, callable $callback): mixed
{
    $directory = sys_get_temp_dir() . '/upsert-env-test-' . uniqid();
    (new Filesystem)->ensureDirectoryExists($directory);
    file_put_contents($directory . '/.env', $envContent);

    $previous = app()->basePath();
    app()->setBasePath($directory);

    try {
        return $callback($directory . '/.env');
    } finally {
        app()->setBasePath($previous);
        (new Filesystem)->deleteDirectory($directory);
    }
}

it('adds and force-updates env variables atomically', function (): void {
    withIsolatedEnvBasePath("APP_NAME=Demo\nFOO=old\n", function (string $envPath): void {
        $messages = [];

        UpsertEnvVariablesAction::run(
            ['FOO' => 'new', 'BAR' => 'added'],
            true,
            function (string $message) use (&$messages): void {
                $messages['warn'][] = $message;
            },
            function (string $message) use (&$messages): void {
                $messages['info'][] = $message;
            },
        );

        $content = file_get_contents($envPath);

        expect($content)->toContain('APP_NAME=Demo')
            ->and($content)->toContain('FOO="new"')
            ->and($content)->toContain('BAR="added"')
            ->and($content)->not->toContain('FOO=old')
            ->and(file_exists($envPath . '.tmp'))->toBeFalse();
    });
});

it('skips existing keys without force and leaves the file untouched', function (): void {
    $original = "APP_NAME=Demo\nFOO=old\n";

    withIsolatedEnvBasePath($original, function (string $envPath) use ($original): void {
        $warnings = [];

        UpsertEnvVariablesAction::run(
            ['FOO' => 'new'],
            false,
            function (string $message) use (&$warnings): void {
                $warnings[] = $message;
            },
            function (string $message): void {},
        );

        expect(file_get_contents($envPath))->toBe($original)
            ->and($warnings)->toHaveCount(1);
    });
});

it('matches existing keys exactly, tolerating spaces around equals', function (): void {
    withIsolatedEnvBasePath("FOO2=keep\nFOO =spaced\n", function (string $envPath): void {
        UpsertEnvVariablesAction::run(
            ['FOO' => 'new'],
            true,
            function (string $message): void {},
            function (string $message): void {},
        );

        $content = file_get_contents($envPath);

        expect($content)->toContain('FOO2=keep')
            ->and($content)->toContain('FOO="new"')
            ->and(preg_match_all('/^FOO\s*=/m', $content))->toBe(1);
    });
});

it('throws when no env file exists', function (): void {
    $directory = sys_get_temp_dir() . '/upsert-env-missing-' . uniqid();
    (new Filesystem)->ensureDirectoryExists($directory);

    $previous = app()->basePath();
    app()->setBasePath($directory);

    try {
        expect(fn () => UpsertEnvVariablesAction::run(
            ['FOO' => 'new'],
            true,
            function (string $message): void {},
            function (string $message): void {},
        ))->toThrow(RuntimeException::class, 'does not exist');
    } finally {
        app()->setBasePath($previous);
        (new Filesystem)->deleteDirectory($directory);
    }
});
