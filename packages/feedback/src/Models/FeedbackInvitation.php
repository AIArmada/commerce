<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Feedback\Enums\FeedbackInvitationStatus;
use AIArmada\Feedback\Models\Concerns\UsesFeedbackUuid;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $feedback_form_id
 * @property string|null $recipient_type
 * @property string|null $recipient_id
 * @property string|null $email
 * @property string|null $phone
 * @property string $token_hash
 * @property FeedbackInvitationStatus $status
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $opened_at
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $expires_at
 * @property array|null $metadata
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read FeedbackForm $form
 * @property-read Model|Eloquent $recipient
 */
final class FeedbackInvitation extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use UsesFeedbackUuid;

    protected static string $ownerScopeConfigKey = 'feedback.owner';

    protected $fillable = [
        'owner_type', 'owner_id',
        'feedback_form_id',
        'recipient_type', 'recipient_id',
        'email', 'phone', 'token_hash', 'status',
        'sent_at', 'opened_at', 'started_at', 'submitted_at',
        'cancelled_at', 'expires_at',
        'metadata',
    ];

    public function getTable(): string
    {
        $prefix = (string) config('feedback.database.table_prefix', '');

        return $prefix . (string) config('feedback.database.tables.invitations', 'feedback_invitations');
    }

    protected function casts(): array
    {
        return [
            'status' => FeedbackInvitationStatus::class,
            'metadata' => 'array',
            'sent_at' => 'immutable_datetime',
            'opened_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(FeedbackForm::class, 'feedback_form_id');
    }

    public function recipient(): MorphTo
    {
        return $this->morphTo('recipient');
    }
}
