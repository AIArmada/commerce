<?php

declare(strict_types=1);

namespace AIArmada\Docs\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class DocEmailTemplate extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

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
        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', (string) $value, $template);
            $template = str_replace('{{ ' . $key . ' }}', (string) $value, $template);
        }

        return $template;
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
