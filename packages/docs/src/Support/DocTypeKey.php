<?php

declare(strict_types=1);

namespace AIArmada\Docs\Support;

use AIArmada\Docs\Enums\DocType;

/**
 * Sanitizes persisted doc types before they are interpolated into config keys.
 *
 * Dots in a doc type would traverse the config array (for example
 * `docs.types.<type>.storage.disk`), so only safe keys are ever used for
 * lookups. Unknown keys fall back to the global defaults.
 */
final class DocTypeKey
{
    /**
     * Return the config-safe key for a doc type, or null when it must not be interpolated.
     */
    public static function sanitize(?string $docType): ?string
    {
        if ($docType === null || $docType === '') {
            return null;
        }

        if (DocType::tryFrom($docType) !== null) {
            return $docType;
        }

        if (preg_match('/^[A-Za-z0-9_-]+$/', $docType) === 1) {
            return $docType;
        }

        return null;
    }
}
