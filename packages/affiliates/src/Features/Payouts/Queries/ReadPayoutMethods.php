<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Queries;

use AIArmada\Affiliates\Enums\PayoutMethodType;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutMethodProfile;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutMethodReader;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final readonly class ReadPayoutMethods implements PayoutMethodReader
{
    public function hasRecords(string $affiliateId): bool
    {
        return $this->methods($affiliateId)->exists();
    }

    public function hasVerified(string $affiliateId): bool
    {
        return $this->methods($affiliateId)->whereNotNull('verified_at')->exists();
    }

    public function defaultType(string $affiliateId): ?string
    {
        $type = $this->methods($affiliateId)->where('is_default', true)->orderBy('id')->value('type');

        return $type instanceof PayoutMethodType ? $type->value : (is_string($type) ? $type : null);
    }

    public function defaultProfile(string $affiliateId): ?PayoutMethodProfile
    {
        $method = $this->methods($affiliateId)->where('is_default', true)->orderBy('id')->first();

        if ($method === null) {
            return null;
        }

        $field = match ($method->type) {
            PayoutMethodType::PayPal => 'email',
            PayoutMethodType::StripeConnect => 'stripe_account_id',
            default => 'account_ref',
        };

        return new PayoutMethodProfile(
            $method->type->value,
            (string) ($method->details['label'] ?? $method->type->label()),
            (string) ($method->details[$field] ?? ''),
        );
    }

    /** @return Builder<AffiliatePayoutMethod> */
    private function methods(string $affiliateId): Builder
    {
        return Str::isUuid($affiliateId)
            ? AffiliatePayoutMethod::query()->where('affiliate_id', $affiliateId)
            : AffiliatePayoutMethod::query()->whereRaw('1 = 0');
    }
}
