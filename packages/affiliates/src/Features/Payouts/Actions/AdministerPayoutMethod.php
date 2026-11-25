<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Actions;

use AIArmada\Affiliates\Contracts\PayoutProcessorRegistry;
use AIArmada\Affiliates\Enums\PayoutMethodType;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutMethodAdministration;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutMethod;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayoutOperation;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class AdministerPayoutMethod implements PayoutMethodAdministration
{
    public function __construct(private PayoutProcessorRegistry $processors) {}

    public function create(string $affiliateId, string $type, array $details, bool $default = false): string
    {
        $type = PayoutMethodType::from($type);
        $details = $this->validateDetails($type, $details);

        return $this->withLockedAffiliate($affiliateId, function (Affiliate $affiliate) use ($type, $details, $default): string {
            if ($default) {
                $this->assertDestinationMutable($affiliate);
                $this->methods($affiliate)->where('is_default', true)->update(['is_default' => false]);
            }

            return (string) $this->methods($affiliate)->create([
                'affiliate_id' => $affiliate->getKey(),
                'type' => $type,
                'details' => $details,
                'is_default' => $default,
            ])->getKey();
        });
    }

    public function replace(string $affiliateId, string $methodId, string $type, array $details): void
    {
        $type = PayoutMethodType::from($type);
        $details = $this->validateDetails($type, $details);

        $this->withLockedAffiliate($affiliateId, function (Affiliate $affiliate) use ($methodId, $type, $details): void {
            $method = $this->methods($affiliate)->lockForUpdate()->findOrFail($methodId);

            if ($method->type === $type && $method->details === $details) {
                return;
            }

            if ($method->is_default) {
                $this->assertDestinationMutable($affiliate);
            }

            $method->update(['type' => $type, 'details' => $details, 'verified_at' => null]);
        });
    }

    public function remove(string $affiliateId, string $methodId): void
    {
        $this->withLockedAffiliate($affiliateId, function (Affiliate $affiliate) use ($methodId): void {
            $method = $this->methods($affiliate)->lockForUpdate()->findOrFail($methodId);

            if ($method->is_default) {
                $this->assertDestinationMutable($affiliate);
            }

            $method->delete();
        });
    }

    public function verify(string $affiliateId, string $methodId): void
    {
        $this->withLockedAffiliate($affiliateId, function (Affiliate $affiliate) use ($methodId): void {
            $method = $this->methods($affiliate)->lockForUpdate()->findOrFail($methodId);
            $this->validateDetails($method->type, $method->details);

            if (! $method->isVerified()) {
                $method->update(['verified_at' => CarbonImmutable::now()]);
            }
        });
    }

    public function selectDefault(string $affiliateId, string $methodId): void
    {
        $this->withLockedAffiliate($affiliateId, function (Affiliate $affiliate) use ($methodId): void {
            $method = $this->methods($affiliate)->lockForUpdate()->findOrFail($methodId);
            $otherDefaults = $this->methods($affiliate)->where('is_default', true)->whereKeyNot($method->getKey());

            if (! $method->is_default || $otherDefaults->exists()) {
                $this->assertDestinationMutable($affiliate);
                $otherDefaults->update(['is_default' => false]);
                $method->update(['is_default' => true]);
            }
        });
    }

    public function setDefault(string $affiliateId, string $type, string $label, string $accountRef): void
    {
        $type = PayoutMethodType::from($type);
        $validated = validator(['label' => $label, 'account_ref' => $accountRef], [
            'label' => ['required', 'string', 'max:255'],
            'account_ref' => ['required', 'string', 'max:255'],
        ])->validate();

        $field = match ($type) {
            PayoutMethodType::PayPal => 'email',
            PayoutMethodType::StripeConnect => 'stripe_account_id',
            default => 'account_ref',
        };

        $details = $this->validateDetails($type, ['label' => $validated['label'], $field => $validated['account_ref']]);

        $this->withLockedAffiliate($affiliateId, function (Affiliate $affiliate) use ($type, $details): void {
            $method = $this->methods($affiliate)->where('is_default', true)->orderBy('id')->lockForUpdate()->first();

            if ($method === null) {
                $this->assertDestinationMutable($affiliate);
                $this->methods($affiliate)->create([
                    'affiliate_id' => $affiliate->getKey(),
                    'type' => $type,
                    'details' => $details,
                    'is_default' => true,
                ]);

                return;
            }

            $changed = $method->type !== $type || $method->details !== $details;
            $otherDefaults = $this->methods($affiliate)->where('is_default', true)->whereKeyNot($method->getKey());

            if ($changed || $otherDefaults->exists()) {
                $this->assertDestinationMutable($affiliate);
                $otherDefaults->update(['is_default' => false]);

                if ($changed) {
                    $method->update(['type' => $type, 'details' => $details, 'verified_at' => null]);
                }
            }
        });
    }

    /** @return Builder<AffiliatePayoutMethod> */
    private function methods(Affiliate $affiliate): Builder
    {
        return AffiliatePayoutMethod::query()->where('affiliate_id', $affiliate->getKey());
    }

    /**
     * @template T
     * @param callable(Affiliate): T $work
     * @return T
     */
    private function withLockedAffiliate(string $affiliateId, callable $work): mixed
    {
        $connection = (new Affiliate)->getConnection();

        if ((new AffiliatePayoutMethod)->getConnection() !== $connection
            || (new AffiliatePayoutOperation)->getConnection() !== $connection) {
            throw new LogicException('Payout method updates require a shared database connection.');
        }

        return $connection->transaction(function () use ($affiliateId, $work): mixed {
            if ((bool) config('affiliates.owner.enabled', false)) {
                OwnerWriteGuard::findOrFailForOwner(Affiliate::class, $affiliateId);
            }

            return $work(Affiliate::query()->lockForUpdate()->findOrFail($affiliateId));
        }, attempts: 3);
    }

    private function assertDestinationMutable(Affiliate $affiliate): void
    {
        if (AffiliatePayoutOperation::query()
            ->withoutOwnerScope()
            ->where('affiliate_id', $affiliate->getKey())
            ->whereIn('status', ['submitting', 'unknown', 'submitted'])
            ->exists()) {
            throw ValidationException::withMessages(['payout_method' => 'Resolve the outstanding provider payout before changing its payment destination.']);
        }
    }

    /**
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    private function validateDetails(PayoutMethodType $type, array $details): array
    {
        validator(['details' => $details], ['details' => ['required', 'array', 'max:30'], 'details.*' => ['nullable', 'string', 'max:1024']])->validate();

        if (! $this->processors->hasProcessor($type->value)) {
            throw ValidationException::withMessages(['payout_method' => 'No processor is configured for this payout method.']);
        }

        if ($this->processors->make($type->value)->validateDetails($details) !== []) {
            throw ValidationException::withMessages(['details' => 'The payout destination details are invalid.']);
        }

        return $details;
    }
}
