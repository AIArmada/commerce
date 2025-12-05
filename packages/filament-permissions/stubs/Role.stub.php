<?php

declare(strict_types=1);

namespace Spatie\Permission\Models;

/**
 * Extended Role model with hierarchy support.
 *
 * @property string|null $parent_role_id
 * @property string|null $template_id
 * @property string|null $description
 * @property int $level
 * @property array<string, mixed>|null $metadata
 * @property bool $is_system
 * @property bool $is_assignable
 */
class Role {}
