<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Actions;

use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Promotions\Events\PromotionCreated;
use AIArmada\Promotions\Models\Promotion;
use AIArmada\Promotions\Support\PromotionInputValidator;
use Illuminate\Support\Facades\DB;

final readonly class CreatePromotion
{
    public function __construct(private PromotionInputValidator $validator) {}

    public function handle(array $data): Promotion
    {
        $this->assertOwnerContext();
        $this->validator->validate($data);
        unset($data['usage_count']);

        $promotion = DB::transaction(fn (): Promotion => Promotion::create($data));

        PromotionCreated::dispatch($promotion);

        return $promotion;
    }

    private function assertOwnerContext(): void
    {
        if (! config('promotions.features.owner.enabled', false)) {
            return;
        }

        $owner = OwnerContext::resolve();

        if ($owner === null && ! OwnerContext::isExplicitGlobal()) {
            throw new NoCurrentOwnerException(
                'Creating promotions requires an owner context or explicit global context. Use OwnerContext::withOwner(...) before calling this action.'
            );
        }
    }
}
