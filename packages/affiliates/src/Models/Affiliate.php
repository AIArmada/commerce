<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\Affiliates\Data\AffiliateData;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\RegistrationApprovalMode;
use AIArmada\Affiliates\Events\AffiliateActivated;
use AIArmada\Affiliates\Events\AffiliateCreated;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\AffiliateStatus;
use AIArmada\Affiliates\States\Pending;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\PublicHandle;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Contacting\Actions\ReplaceGeneralContactMethods;
use AIArmada\Contacting\Concerns\HasContactMethods;
use AIArmada\Contacting\Concerns\HasSocialProfiles;
use AIArmada\Vouchers\Models\Voucher;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LogicException;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\ModelStates\HasStates;

/**
 * @property string $id
 * @property string $code
 * @property string $handle
 * @property string $name
 * @property string|null $description
 * @property AffiliateStatus $status
 * @property string $registration_approval_mode
 * @property CommissionType $commission_type
 * @property int $commission_rate
 * @property string $currency
 * @property string|null $purpose
 * @property string|null $parent_affiliate_id
 * @property string|null $rank_id
 * @property int $network_depth
 * @property int $direct_downline_count
 * @property int $total_downline_count
 * @property string|null $default_voucher_code
 * @property string|null $payout_terms
 * @property string|null $tracking_domain
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property array<string, mixed>|null $metadata
 * @property CarbonInterface|null $activated_at
 * @property CarbonInterface|null $deactivated_at
 * @property CarbonInterface|null $paused_at
 * @property CarbonInterface|null $identity_sealed_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read string|null $contact_email Alias for the primary email contact method
 * @property-read string|null $website_url Alias for the primary website contact method
 * @property-read Affiliate|null $parent
 * @property-read AffiliateRank|null $rank
 * @property-read Collection<int, Affiliate> $children
 * @property-read Collection<int, AffiliateAttribution> $attributions
 * @property-read Collection<int, AffiliateConversion> $conversions
 * @property-read Collection<int, AffiliateBalance> $balances
 * @property-read Collection<int, AffiliatePayoutMethod> $payoutMethods
 * @property-read Collection<int, AffiliatePayoutHold> $payoutHolds
 * @property-read Collection<int, AffiliatePayout> $payouts
 * @property-read Collection<int, AffiliateLink> $links
 * @property-read Collection<int, AffiliateProgram> $programs
 * @property-read Collection<int, Voucher> $vouchers
 * @property-read Model|null $owner
 */
class Affiliate extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasContactMethods;
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasSocialProfiles;
    use HasStates;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'affiliates.owner';

    public const string PURPOSE_GENERAL = 'general';

    private ?string $pendingContactEmail = null;

    private bool $contactEmailDirty = false;

    private ?string $pendingWebsiteUrl = null;

    private bool $websiteUrlDirty = false;

    protected $fillable = [
        'code',
        'handle',
        'name',
        'description',
        'status',
        'registration_approval_mode',
        'commission_type',
        'commission_rate',
        'currency',
        'purpose',
        'parent_affiliate_id',
        'rank_id',
        'network_depth',
        'direct_downline_count',
        'total_downline_count',
        'default_voucher_code',
        'payout_terms',
        'tracking_domain',
        'contact_email',
        'website_url',
        'metadata',
        'activated_at',
        'deactivated_at',
        'paused_at',
    ];

    public function getAuditInclude(): array
    {
        return [
            'code',
            'handle',
            'name',
            'description',
            'status',
            'commission_type',
            'commission_rate',
            'currency',
            'purpose',
            'parent_affiliate_id',
            'rank_id',
            'network_depth',
            'direct_downline_count',
            'total_downline_count',
            'default_voucher_code',
            'payout_terms',
            'tracking_domain',
            'owner_type',
            'owner_id',
            'activated_at',
            'deactivated_at',
            'paused_at',
            'identity_sealed_at',
        ];
    }

    public function toData(): AffiliateData
    {
        return new AffiliateData(
            id: (string) $this->getKey(),
            code: (string) $this->code,
            name: (string) $this->name,
            status: $this->status->getValue(),
            commissionType: ($this->commission_type ?? CommissionType::Percentage)->value,
            commissionRate: (int) $this->commission_rate,
            currency: (string) $this->currency,
            defaultVoucherCode: $this->default_voucher_code,
            metadata: $this->metadata,
        );
    }

    public function getTable(): string
    {
        return config('affiliates.database.tables.affiliates', parent::getTable());
    }

    /**
     * @return HasMany<AffiliateAttribution, $this>
     */
    public function attributions(): HasMany
    {
        return $this->hasMany(AffiliateAttribution::class);
    }

    /**
     * @return HasMany<AffiliateConversion, $this>
     */
    public function conversions(): HasMany
    {
        return $this->hasMany(AffiliateConversion::class);
    }

    /**
     * @return MorphMany<AffiliatePayout, $this>
     */
    public function payouts(): MorphMany
    {
        return $this->morphMany(AffiliatePayout::class, 'payee');
    }

    /**
     * @return HasMany<AffiliateLink, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(AffiliateLink::class);
    }

    /**
     * @return HasMany<AffiliateProgramMembership, $this>
     */
    public function programMemberships(): HasMany
    {
        return $this->hasMany(AffiliateProgramMembership::class, 'affiliate_id')
            ->whereIn('program_id', AffiliateProgram::query()->select('id'));
    }

    /**
     * @return BelongsToMany<AffiliateProgram, $this, AffiliateProgramMembership, 'pivot'>
     */
    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(
            AffiliateProgram::class,
            config('affiliates.database.tables.program_memberships', 'affiliate_program_memberships'),
            'affiliate_id',
            'program_id'
        )
            ->using(AffiliateProgramMembership::class)
            ->withPivot(['id', 'tier_id', 'status', 'applied_at', 'approved_at', 'rejected_at', 'suspended_at', 'expires_at'])
            ->withTimestamps();
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_affiliate_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_affiliate_id');
    }

    /**
     * @return BelongsTo<AffiliateRank, $this>
     */
    public function rank(): BelongsTo
    {
        return $this->belongsTo(AffiliateRank::class, 'rank_id');
    }

    /**
     * @return HasMany<AffiliateBalance, $this>
     */
    public function balances(): HasMany
    {
        return $this->hasMany(AffiliateBalance::class);
    }

    /**
     * Route mail notifications to the primary email contact method.
     */
    public function routeNotificationForMail(): ?string
    {
        $email = $this->contact_email;

        return is_string($email) && $email !== '' ? $email : null;
    }

    public function balanceFor(string $currency): ?AffiliateBalance
    {
        return $this->balances()->where('currency', mb_strtoupper($currency))->first();
    }

    /**
     * Current balance shortcut for single-currency affiliates.
     *
     * Multi-currency merchants should use balances() or balanceFor().
     *
     * @return HasOne<AffiliateBalance, $this>
     */
    public function balance(): HasOne
    {
        return $this->hasOne(AffiliateBalance::class)->latestOfMany();
    }

    /**
     * @return HasMany<AffiliatePayoutMethod, $this>
     */
    public function payoutMethods(): HasMany
    {
        return $this->hasMany(AffiliatePayoutMethod::class);
    }

    /**
     * @return HasMany<AffiliatePayoutHold, $this>
     */
    public function payoutHolds(): HasMany
    {
        return $this->hasMany(AffiliatePayoutHold::class);
    }

    /**
     * Get all vouchers linked to this affiliate (when aiarmada/vouchers is installed).
     *
     * @return HasMany<Voucher, $this>|HasMany<Model, $this>
     */
    public function vouchers(): HasMany
    {
        if (\class_exists(Voucher::class)) {
            return $this->hasMany(Voucher::class, 'affiliate_id');
        }

        // Fallback to prevent errors when vouchers package not installed
        return $this->hasMany(Model::class, 'affiliate_id');
    }

    public function hasActivePayoutHold(): bool
    {
        return $this->payoutHolds()
            ->whereNull('released_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', CarbonImmutable::now());
            })
            ->exists();
    }

    public function canRequestPayout(?string $currency = null): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($this->hasActivePayoutHold()) {
            return false;
        }

        if ($currency !== null) {
            return $this->balanceFor($currency)?->canRequestPayout() ?? false;
        }

        return $this->balances()->get()->contains(fn (AffiliateBalance $balance): bool => $balance->canRequestPayout());
    }

    public function isActive(): bool
    {
        return $this->status instanceof Active;
    }

    public function isOpenPending(): bool
    {
        return $this->status instanceof Pending
            && RegistrationApprovalMode::tryFrom((string) $this->registration_approval_mode) === RegistrationApprovalMode::Open;
    }

    public function canBeAttributed(): bool
    {
        return $this->isActive() || $this->isOpenPending();
    }

    public function canReceivePayout(): bool
    {
        return $this->status instanceof Active && ! $this->isPaused();
    }

    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    public function isPaused(): bool
    {
        return $this->paused_at !== null;
    }

    public function scopeForOwner(Builder $query, Model | string | null $owner = OwnerContext::CURRENT, bool $includeGlobal = false): Builder
    {
        if (! config('affiliates.owner.enabled', false)) {
            return $query;
        }

        $includeGlobal = $includeGlobal && (bool) config('affiliates.owner.include_global', false);

        /** @var Builder<static> $scoped */
        $scoped = $this->baseScopeForOwner($query, $owner, $includeGlobal);

        return $scoped;
    }

    /**
     * Commercial-participation scope: unmarked (null) or explicitly general
     * profiles only. Host-tagged non-commercial profiles — attribution-only,
     * reward-recipient, system — are excluded from bonuses, ranks, uplines,
     * downlines, sweeps, and unfiltered payout queues. The package never
     * interprets host purpose values beyond this test.
     */
    public function scopeWhereCommercialPurpose(Builder $query): Builder
    {
        $column = $query->getModel()->qualifyColumn('purpose');
        $wrappedColumn = $query->getQuery()->getGrammar()->wrap($column);

        return $query->where(static function (Builder $nested) use ($column, $wrappedColumn): void {
            $nested->whereNull($column)->orWhereRaw("LOWER(TRIM({$wrappedColumn})) = ?", [self::PURPOSE_GENERAL]);
        });
    }

    public function isCommercialPurpose(): bool
    {
        $purpose = $this->getAttribute('purpose');

        return $purpose === null || (is_string($purpose) && mb_strtolower(mb_trim($purpose, ' ')) === self::PURPOSE_GENERAL);
    }

    protected static function booted(): void
    {
        self::saving(function (self $affiliate): void {
            if ($affiliate->exists && ! $affiliate->isDirty('handle')) {
                return;
            }

            $provided = $affiliate->getAttribute('handle');
            if (! $affiliate->exists && ($provided === null || $provided === '')) {
                $affiliate->handle = PublicHandle::generate($affiliate->name);
            } else {
                Validator::make(['handle' => $provided], ['handle' => ['required', 'string']])->validate();
                if (! config('affiliates.handles.allow_custom', true)) {
                    throw ValidationException::withMessages(['handle' => 'Custom handles are disabled.']);
                }
                $affiliate->handle = PublicHandle::normalize($provided);
            }

            Validator::make(['handle' => $affiliate->handle], ['handle' => [
                ...PublicHandle::rules(),
                Rule::unique($affiliate->getTable(), 'handle')->ignore($affiliate->getKey()),
            ]])->validate();
        });

        self::creating(function (self $affiliate): void {
            if (! config('affiliates.owner.enabled', false)) {
                return;
            }

            if ($affiliate->owner_id !== null) {
                return;
            }

            if (! config('affiliates.owner.auto_assign_on_create', true)) {
                return;
            }

            $owner = OwnerContext::resolve();

            if ($owner) {
                $affiliate->owner_type = $owner->getMorphClass();
                $affiliate->owner_id = $owner->getKey();
            }
        });

        self::created(function (self $affiliate): void {
            AffiliateCreated::dispatch($affiliate);
        });

        self::updating(function (self $affiliate): void {
            if ($affiliate->isDirty('identity_sealed_at') && $affiliate->getRawOriginal('identity_sealed_at') !== null) {
                throw new LogicException('The identity seal is permanent.');
            }

            if ($affiliate->isDirty('registration_approval_mode')) {
                throw new LogicException('The registration approval mode is immutable after creation.');
            }

            if ($affiliate->isDirty('status') && $affiliate->status instanceof Active && $affiliate->activated_at === null) {
                $affiliate->activated_at = CarbonImmutable::now();
            }
        });

        self::updated(function (self $affiliate): void {
            // Fire activated event when status changes to Active
            if ($affiliate->wasChanged('status') && $affiliate->status instanceof Active) {
                AffiliateActivated::dispatch($affiliate);
            }
        });

        self::saved(function (self $affiliate): void {
            $affiliate->syncPendingContacts();
        });

        self::deleting(function (self $affiliate): void {
            if ($affiliate->identity_sealed_at !== null
                || $affiliate->conversions()->exists()
                || $affiliate->payouts()->exists()
                || $affiliate->attributions()->exists()
                || $affiliate->links()->exists()
                || $affiliate->balances()->exists()
                || $affiliate->payoutMethods()->exists()
                || $affiliate->payoutHolds()->exists()
                || $affiliate->children()->exists()) {
                throw new LogicException('Affiliate removal must preserve history and clean up owned records through the affiliate removal action.');
            }
        });
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function contactEmail(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->resolveContactEmail(),
        );
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->resolveContactEmail(),
        );
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function websiteUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->resolveWebsiteUrl(),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AffiliateStatus::class,
            'commission_type' => CommissionType::class,
            'network_depth' => 'integer',
            'direct_downline_count' => 'integer',
            'total_downline_count' => 'integer',
            'metadata' => 'array',
            'activated_at' => 'immutable_datetime',
            'deactivated_at' => 'immutable_datetime',
            'paused_at' => 'immutable_datetime',
            'identity_sealed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function getLoggableAttributes(): array
    {
        return $this->getAuditInclude();
    }

    protected function getActivityLogName(): string
    {
        return 'affiliates';
    }

    private function resolveContactEmail(): ?string
    {
        if ($this->contactEmailDirty) {
            return $this->pendingContactEmail;
        }

        return OwnerContext::withOwner($this->owner, function (): ?string {
            $contactMethod = $this->contactMethods()
                ->where('type', 'email')
                ->where('purpose', 'general')
                ->orderByDesc('is_primary')
                ->orderBy('sort_order')
                ->first();

            return $this->cleanString($contactMethod?->normalized_value ?? $contactMethod?->value);
        });
    }

    private function resolveWebsiteUrl(): ?string
    {
        if ($this->websiteUrlDirty) {
            return $this->pendingWebsiteUrl;
        }

        return OwnerContext::withOwner($this->owner, function (): ?string {
            $contactMethod = $this->contactMethods()
                ->where('type', 'website')
                ->where('purpose', 'general')
                ->orderByDesc('is_primary')
                ->orderBy('sort_order')
                ->first();

            return $this->cleanString($contactMethod?->normalized_value ?? $contactMethod?->value);
        });
    }

    private function syncPendingContacts(): void
    {
        $changes = [];

        if ($this->contactEmailDirty) {
            $changes['email'] = $this->pendingContactEmail;
        }

        if ($this->websiteUrlDirty) {
            $changes['website'] = $this->pendingWebsiteUrl;
        }

        if ($changes === []) {
            return;
        }

        OwnerContext::withOwner($this->owner, fn () => app(ReplaceGeneralContactMethods::class)->execute($this, $changes));

        $this->pendingContactEmail = null;
        $this->contactEmailDirty = false;
        $this->pendingWebsiteUrl = null;
        $this->websiteUrlDirty = false;
    }

    public function setContactEmailAttribute(mixed $value): void
    {
        $this->pendingContactEmail = $this->cleanString($value);
        $this->contactEmailDirty = true;
    }

    public function setEmailAttribute(mixed $value): void
    {
        $this->setContactEmailAttribute($value);
    }

    public function setWebsiteUrlAttribute(mixed $value): void
    {
        $this->pendingWebsiteUrl = $this->cleanString($value);
        $this->websiteUrlDirty = true;
    }

    private function cleanString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_scalar($value)) {
            return null;
        }

        $trimmed = mb_trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
