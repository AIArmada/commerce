<?php

declare(strict_types=1);

namespace AIArmada\Docs\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $view_name
 * @property string $doc_type
 * @property bool $is_default
 * @property array<string, mixed>|null $settings
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class DocTemplate extends Model
{
    use HasFactory;
    use HasOwner;
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'view_name',
        'doc_type',
        'is_default',
        'owner_type',
        'owner_id',
        'settings',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'settings' => 'array',
    ];

    public function getTable(): string
    {
        return config('docs.database.tables.doc_templates', 'doc_templates');
    }

    public function docs(): HasMany
    {
        return $this->hasMany(Doc::class);
    }

    /**
     * Set this template as default within the same owner context
     */
    public function setAsDefault(): void
    {
        // Build query to remove default from other templates of the same type
        $query = static::where('id', '!=', $this->id)
            ->where('doc_type', $this->doc_type);

        // Scope to same owner context
        if ($this->owner_type !== null && $this->owner_id !== null) {
            $query->where('owner_type', $this->owner_type)
                ->where('owner_id', $this->owner_id);
        } else {
            $query->whereNull('owner_type');
        }

        $query->update(['is_default' => false]);

        // Set this as default
        $this->update(['is_default' => true]);
    }

    protected static function booted(): void
    {
        static::deleting(function (DocTemplate $template): void {
            // Nullify the template reference on associated docs rather than deleting them
            $template->docs()->update(['doc_template_id' => null]);
        });
    }

    /**
     * Scope to get default template
     *
     * @param  \Illuminate\Database\Eloquent\Builder<DocTemplate>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function default($query, ?string $docType = null): ?self
    {
        $query = $query->where('is_default', true);

        if ($docType) {
            $query->where('doc_type', $docType);
        }

        return $query->first();
    }
}
