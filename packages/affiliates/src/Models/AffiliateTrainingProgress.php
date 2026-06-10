<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\Affiliates\Models\Concerns\ScopesByAffiliateOwner;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string $affiliate_id
 * @property string $module_id
 * @property int $progress_percent
 * @property int|null $last_position
 * @property int|null $quiz_score
 * @property int $quiz_attempts
 * @property string|null $certificate_url
 * @property Carbon|null $completed_at
 * @property Carbon|null $quiz_passed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Affiliate $affiliate
 * @property-read AffiliateTrainingModule $module
 */
class AffiliateTrainingProgress extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasUuids;
    use LogsCommerceActivity;
    use ScopesByAffiliateOwner;

    protected $fillable = [
        'affiliate_id',
        'module_id',
        'progress_percent',
        'last_position',
        'quiz_score',
        'quiz_attempts',
        'certificate_url',
        'completed_at',
        'quiz_passed_at',
    ];

    protected $casts = [
        'progress_percent' => 'integer',
        'last_position' => 'integer',
        'quiz_score' => 'integer',
        'quiz_attempts' => 'integer',
        'completed_at' => 'immutable_datetime',
        'quiz_passed_at' => 'immutable_datetime',
    ];

    public function getTable(): string
    {
        return config('affiliates.database.tables.training_progress', 'affiliate_training_progress');
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'affiliate_id');
    }

    /**
     * @return BelongsTo<AffiliateTrainingModule, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(AffiliateTrainingModule::class, 'module_id');
    }

    protected function getActivityLogName(): string
    {
        return 'affiliates';
    }
}
