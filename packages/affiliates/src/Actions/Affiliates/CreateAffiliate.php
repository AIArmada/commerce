<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Affiliates;

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\RegistrationApprovalMode;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Contacting\Data\ContactMethodData;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Create a new affiliate.
 */
final class CreateAffiliate
{
    use AsAction;

    public function __construct(
        private readonly GenerateAffiliateCode $generateCode,
    ) {}

    /**
     * Create a new affiliate with the given data.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?Model $owner = null): Affiliate
    {
        $name = $this->resolveName($data);
        $commissionType = $this->resolveCommissionType($data);
        $commissionRate = $this->resolveCommissionRate($data, $commissionType);
        $parentId = $this->resolveParentId($data);

        $codeProvided = array_key_exists('code', $data) && $data['code'] !== null;
        $attempts = $codeProvided ? 1 : 3;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return DB::transaction(function () use ($data, $owner, $name, $commissionType, $commissionRate, $parentId): Affiliate {
                    $status = $this->getApprovalMode()->defaultStatus();

                    $affiliate = new Affiliate([
                        'code' => $data['code'] ?? $this->generateCode->handle($name),
                        'name' => $name,
                        'description' => $data['description'] ?? null,
                        'status' => $status,
                        'commission_type' => $commissionType,
                        'commission_rate' => $commissionRate,
                        'currency' => $data['currency'] ?? config('affiliates.currency.default', 'MYR'),
                        'parent_affiliate_id' => $parentId,
                        'metadata' => $data['metadata'] ?? [],
                    ]);

                    if ($owner) {
                        $affiliate->owner_type = $owner->getMorphClass();
                        $affiliate->owner_id = $owner->getKey();
                    }

                    if ($status === Active::class) {
                        $affiliate->activated_at = CarbonImmutable::now();
                    }

                    $affiliate->save();

                    if ($email = $data['contact_email'] ?? null) {
                        OwnerContext::withOwner($owner, fn () => $affiliate->addContactMethod(ContactMethodData::email($email, 'general')));
                    }

                    if ($website = $data['website_url'] ?? null) {
                        OwnerContext::withOwner($owner, fn () => $affiliate->addContactMethod(ContactMethodData::website($website)));
                    }

                    if ($phone = $data['phone'] ?? null) {
                        OwnerContext::withOwner($owner, fn () => $affiliate->addContactMethod(ContactMethodData::phone($phone, countryCode: 'MY', purpose: 'general')));
                    }

                    return $affiliate;
                });
            } catch (QueryException $exception) {
                if ($codeProvided || ! $this->isUniqueConstraintViolation($exception) || $attempt === $attempts) {
                    throw $exception;
                }
            }
        }

        throw new LogicException('Unable to generate a unique affiliate code.');
    }

    private function getApprovalMode(): RegistrationApprovalMode
    {
        $mode = config('affiliates.registration.approval_mode', 'admin');

        return RegistrationApprovalMode::tryFrom($mode) ?? RegistrationApprovalMode::Admin;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveName(array $data): string
    {
        $name = $data['name'] ?? null;

        if (! is_string($name) || mb_trim($name) === '') {
            throw new InvalidArgumentException('Affiliate name is required.');
        }

        return mb_trim($name);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveCommissionType(array $data): CommissionType
    {
        $type = $data['commission_type'] ?? $this->getDefaultCommissionType();

        if ($type instanceof CommissionType) {
            return $type;
        }

        if (is_string($type) && CommissionType::tryFrom($type) !== null) {
            return CommissionType::from($type);
        }

        throw new InvalidArgumentException('Affiliate commission type is invalid.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveCommissionRate(array $data, CommissionType $type): int
    {
        $rate = $data['commission_rate'] ?? $this->getDefaultCommissionRate();

        if (! is_numeric($rate) || (int) $rate < 0) {
            throw new InvalidArgumentException('Affiliate commission rate must be zero or greater.');
        }

        $rate = (int) $rate;

        if ($type === CommissionType::Percentage && $rate > 10000) {
            throw new InvalidArgumentException('Affiliate percentage commission rate must not exceed 10000 basis points.');
        }

        return $rate;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveParentId(array $data): ?string
    {
        $parentId = $data['parent_affiliate_id'] ?? null;

        if ($parentId === null || $parentId === '') {
            return null;
        }

        if (! is_string($parentId) && ! is_int($parentId)) {
            throw new InvalidArgumentException('Parent affiliate id is invalid.');
        }

        if (config('affiliates.owner.enabled', false)) {
            $parent = OwnerWriteGuard::findOrFailForOwner(
                Affiliate::class,
                $parentId,
                message: 'Parent affiliate is not accessible in the current owner scope.',
            );

            return (string) $parent->getKey();
        }

        $parent = Affiliate::query()->find($parentId);

        if (! $parent instanceof Affiliate) {
            throw new InvalidArgumentException('Parent affiliate does not exist.');
        }

        return (string) $parent->getKey();
    }

    private function getDefaultCommissionType(): CommissionType
    {
        $type = config('affiliates.registration.default_commission_type', 'percentage');

        return CommissionType::tryFrom($type) ?? CommissionType::Percentage;
    }

    private function getDefaultCommissionRate(): int
    {
        return (int) config('affiliates.registration.default_commission_rate', 1000);
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true);
    }
}
