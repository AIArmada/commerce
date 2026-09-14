<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Support;

use AIArmada\CommerceSupport\Support\FilamentPermission;

/**
 * Dedicated ability namespaces for the documents UI.
 *
 * These intentionally do not reuse the CHIP `purchase.*` namespace: granting
 * payment visibility must never grant document access.
 */
final class DocPermissions
{
    public const string DOCUMENT = 'document';

    public const string DOCUMENT_TEMPLATE = 'document_template';

    public const string DOCUMENT_SEQUENCE = 'document_sequence';

    public const string DOCUMENT_EMAIL_TEMPLATE = 'document_email_template';

    public const string DOCUMENT_APPROVAL = 'document_approval';

    public static function allows(string $namespace, string $action): bool
    {
        return FilamentPermission::hasAbility("{$namespace}.{$action}");
    }
}
