<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Actions;

use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Promotions\Enums\PromotionType;
use AIArmada\Promotions\Models\Promotion;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

final class IssueVouchersFromPromotion
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $overrides
     * @return Collection<int, mixed>
     */
    public function handle(
        Promotion $promotion,
        int $count = 1,
        ?string $codePrefix = null,
        array $overrides = [],
    ): Collection {
        if ($count < 1 || $count > 100) {
            throw new InvalidArgumentException('Voucher issue count must be between 1 and 100.');
        }

        $voucherService = $this->resolveVoucherService();
        $this->assertSharedConnection($promotion);

        return $promotion->getConnection()->transaction(function () use ($count, $codePrefix, $overrides, $promotion, $voucherService): Collection {
            $promotion = Promotion::query()->whereKey($promotion->getKey())->lockForUpdate()->firstOrFail();
            $owner = OwnerContext::resolve();

            if ($promotion->owner_type === null && $promotion->owner_id === null) {
                if (! OwnerContext::isExplicitGlobal()) {
                    throw new NoCurrentOwnerException('Issuing vouchers from a global promotion requires explicit global context.');
                }
            } elseif ($owner === null
                || $owner->getMorphClass() !== $promotion->owner_type
                || (string) $owner->getKey() !== (string) $promotion->owner_id) {
                throw new AuthorizationException('Promotion is not accessible in the current owner scope.');
            }

            $issued = collect();

            for ($sequence = 1; $sequence <= $count; $sequence++) {
                $payload = array_replace_recursive(
                    $this->buildVoucherPayload($promotion, $sequence, $codePrefix),
                    $overrides,
                );

                $payload['usage_limit'] = 1;
                $payload['promotion_id'] = $promotion->id;
                $payload['owner_type'] = $promotion->owner_type;
                $payload['owner_id'] = $promotion->owner_id;

                $issued->push($voucherService->create($payload));
            }

            return $issued;
        });
    }

    private function assertSharedConnection(Promotion $promotion): void
    {
        $voucherClass = 'AIArmada\\\\Vouchers\\\\Models\\\\Voucher';

        if (! is_subclass_of($voucherClass, Model::class)) {
            throw new RuntimeException('Voucher issuance requires the vouchers package to be installed.');
        }

        $voucher = new $voucherClass;

        if ($voucher->getConnection() !== $promotion->getConnection()) {
            throw new LogicException('Promotion voucher issuance requires a shared database connection.');
        }
    }

    /**
     * @return object{create: callable|mixed}
     */
    private function resolveVoucherService(): object
    {
        $contract = 'AIArmada\\Vouchers\\Runtime\\VoucherServiceInterface';

        if (! interface_exists($contract)) {
            throw new RuntimeException('Voucher issuance requires the vouchers package to be installed.');
        }

        /** @var object $voucherService */
        $voucherService = app($contract);

        return $voucherService;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildVoucherPayload(Promotion $promotion, int $sequence, ?string $codePrefix): array
    {
        $currency = (string) config('promotions.defaults.currency', config('vouchers.default_currency', 'MYR'));

        return [
            'code' => $this->buildVoucherCode($promotion, $sequence, $codePrefix),
            'name' => $promotion->name . ($sequence > 1 ? " #{$sequence}" : ''),
            'description' => $promotion->description,
            'type' => $this->mapVoucherType($promotion->type),
            'value' => $this->mapVoucherValue($promotion),
            'value_config' => null,
            'currency' => $currency,
            'min_cart_value' => $promotion->min_purchase_amount,
            'usage_limit' => 1,
            'starts_at' => $promotion->starts_at,
            'expires_at' => $promotion->ends_at,
            'status' => 'active',
            'owner_type' => $promotion->owner_type,
            'owner_id' => $promotion->owner_id,
            'target_definition' => $this->buildTargetDefinition($promotion),
            'promotion_id' => $promotion->id,
        ];
    }

    private function buildVoucherCode(Promotion $promotion, int $sequence, ?string $codePrefix): string
    {
        $basePrefix = $this->normalizeCodePrefix($codePrefix ?? $promotion->code ?? $promotion->name);
        $randomSuffix = Str::upper(Str::random(8));
        $sequencePrefix = $sequence > 1 ? "{$sequence}-" : '';

        if ($basePrefix === '') {
            return $sequencePrefix . $randomSuffix;
        }

        return "{$basePrefix}-{$sequencePrefix}{$randomSuffix}";
    }

    private function normalizeCodePrefix(string $value): string
    {
        return mb_trim(Str::upper(Str::slug($value, '-')), '-');
    }

    private function mapVoucherType(PromotionType $type): string
    {
        return match ($type) {
            PromotionType::Percentage => 'percentage',
            PromotionType::Fixed => 'fixed',
        };
    }

    private function mapVoucherValue(Promotion $promotion): int
    {
        return match ($promotion->type) {
            PromotionType::Percentage => $promotion->discount_value * 100,
            PromotionType::Fixed => $promotion->discount_value,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildTargetDefinition(Promotion $promotion): ?array
    {
        if (! is_array($promotion->conditions) || $promotion->conditions === []) {
            return null;
        }

        return ['targeting' => $promotion->conditions];
    }
}
