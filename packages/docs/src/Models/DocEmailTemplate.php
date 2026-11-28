<?php

declare(strict_types=1);

namespace AIArmada\Docs\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Support\OwnerScopeKey;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeKey;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Email template for document communications.
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string $doc_type
 * @property string $trigger
 * @property string $subject
 * @property string $body
 * @property bool $is_active
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $owner_scope
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class DocEmailTemplate extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasOwnerScopeKey;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'docs.owner';

    protected $fillable = [
        'name',
        'slug',
        'doc_type',
        'trigger',
        'subject',
        'body',
        'is_active',
    ];

    public function getTable(): string
    {
        return config('docs.database.tables.doc_email_templates', 'docs_email_templates');
    }

    protected static function booted(): void
    {
        // creating (not saving): runs after HasOwner's auto-assign (trait boots
        // register first). Owner tuples are immutable afterwards.
        self::creating(function (DocEmailTemplate $template): void {
            $template->owner_scope = OwnerScopeKey::forAttributes($template->getAttributes());
        });
    }

    /**
     * Render the subject with variables.
     *
     * @param  array<string, mixed>  $variables
     */
    public function renderSubject(array $variables = []): string
    {
        return $this->renderTemplate($this->subject, $variables);
    }

    /**
     * Render the body with variables.
     *
     * @param  array<string, mixed>  $variables
     */
    public function renderBody(array $variables = []): string
    {
        return $this->renderTemplate($this->body, $variables);
    }

    /**
     * Simple variable replacement.
     *
     * @param  array<string, mixed>  $variables
     */
    protected function renderTemplate(string $template, array $variables): string
    {
        // Substitute each placeholder once. A value containing another
        // placeholder is data, not a second template.
        return preg_replace_callback('/\{\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}/u', static function (array $matches) use ($variables): string {
            $key = $matches[1];

            if (! array_key_exists($key, $variables)) {
                return $matches[0];
            }

            $value = $variables[$key];

            return is_scalar($value) || $value === null ? (string) $value : '';
        }, $template) ?? $template;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
