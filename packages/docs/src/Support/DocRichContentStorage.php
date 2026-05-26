<?php

declare(strict_types=1);

namespace AIArmada\Docs\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerFilesystem;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class DocRichContentStorage
{
    public static function directory(): string
    {
        $directory = self::configuredDirectory();

        if (! (bool) config('docs.owner.enabled', false)) {
            return $directory;
        }

        $owner = OwnerContext::resolve();

        OwnerContext::assertResolvedOrExplicitGlobal(
            $owner,
            'Document rich-content attachments require an owner context.',
        );

        return OwnerFilesystem::path($owner, $directory);
    }

    public static function isAllowedFileId(mixed $file): bool
    {
        if (! is_string($file) || $file === '') {
            return false;
        }

        if (str_contains($file, '\\') || str_contains($file, '..') || str_starts_with($file, '/')) {
            return false;
        }

        $directory = self::directory();

        return Str::startsWith($file, $directory . '/');
    }

    private static function configuredDirectory(): string
    {
        $directory = mb_trim((string) config('docs.storage.rich_content_path', 'docs/rich-content'), '/');
        $directory = str_replace('\\', '/', $directory);

        if (str_contains($directory, '..')) {
            throw new InvalidArgumentException('Document rich-content directory cannot contain traversal segments.');
        }

        return $directory === '' ? 'docs/rich-content' : $directory;
    }
}
