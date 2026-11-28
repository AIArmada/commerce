<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Approvals\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Docs\Features\Approvals\Support\WorkflowConditions;
use AIArmada\Docs\Models\Doc;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string $workflow_id
 * @property string $name
 * @property int $order
 * @property string $action_type
 * @property array<string, mixed>|null $action_config
 * @property array<string, mixed>|null $conditions
 * @property bool $is_required
 * @property int|null $timeout_hours
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $timed_out_at
 * @property CarbonImmutable|null $escalated_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read DocWorkflow $workflow
 */
final class DocWorkflowStep extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'docs.owner';

    public const ACTION_APPROVAL = 'approval';

    public const ACTION_NOTIFICATION = 'notification';

    public const ACTION_STATUS_CHANGE = 'status_change';

    public const ACTION_WEBHOOK = 'webhook';

    protected $fillable = [
        'workflow_id',
        'name',
        'order',
        'action_type',
        'action_config',
        'conditions',
        'is_required',
        'timeout_hours',
    ];

    public function getTable(): string
    {
        $tables = config('docs.database.tables', []);
        $prefix = config('docs.database.table_prefix', 'docs_');

        return $tables['workflow_steps'] ?? $prefix . 'workflow_steps';
    }

    /**
     * @return BelongsTo<DocWorkflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(DocWorkflow::class, 'workflow_id');
    }

    /**
     * Check if this step requires approval.
     */
    public function requiresApproval(): bool
    {
        return $this->action_type === self::ACTION_APPROVAL;
    }

    /**
     * Check if this step sends notifications.
     */
    public function sendsNotification(): bool
    {
        return $this->action_type === self::ACTION_NOTIFICATION;
    }

    /**
     * Get the approvers for this step.
     *
     * @return array<int, string>
     */
    public function getApprovers(): array
    {
        if ($this->action_type !== self::ACTION_APPROVAL) {
            return [];
        }

        $config = $this->action_config ?? [];

        return $config['approvers'] ?? [];
    }

    /**
     * Get the notification recipients for this step.
     *
     * @return array<int, string>
     */
    public function getNotificationRecipients(): array
    {
        $config = $this->action_config ?? [];

        return $config['recipients'] ?? [];
    }

    /**
     * Check if conditions are met for this step.
     */
    public function conditionsMet(Doc $doc): bool
    {
        if (config('docs.owner.enabled', false) && ! WorkflowConditions::matchesOwner(
            $this->owner_type,
            $this->owner_id,
            $doc->owner_type,
            $doc->owner_id,
            (bool) config('docs.owner.include_global', false),
        )) {
            return false;
        }

        return WorkflowConditions::matches($doc->attributesToArray(), $this->conditions ?? []);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'action_config' => 'array',
            'conditions' => 'array',
            'is_required' => 'boolean',
            'timeout_hours' => 'integer',
            'completed_at' => 'immutable_datetime',
            'timed_out_at' => 'immutable_datetime',
            'escalated_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
