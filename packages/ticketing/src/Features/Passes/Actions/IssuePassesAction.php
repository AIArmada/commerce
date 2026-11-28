<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\Passes\Actions;

use AIArmada\Ticketing\Contracts\PassIssuerInterface;
use AIArmada\Ticketing\Features\Passes\Models\Pass;
use AIArmada\Ticketing\Features\Passes\Models\PassHolder;
use AIArmada\Ticketing\Support\HolderAttributesValidator;
use AIArmada\Ticketing\Support\PassIssuanceContext;
use Illuminate\Database\Eloquent\Collection;

final readonly class IssuePassesAction
{
    public function __construct(private PassIssuerInterface $issuer) {}

    /** @return Collection<int, Pass> */
    public function handle(PassIssuanceContext $context): Collection
    {
        return (new Pass)->getConnection()->transaction(function () use ($context): Collection {
            $passes = $this->issuer->issuePassesFor($context);

            foreach ($passes->values() as $index => $pass) {
                $holderAttributes = $this->resolveHolderAttributesForPass($context->holderAttributes, $index);

                if ($holderAttributes !== []) {
                    $this->createHolder($pass, $holderAttributes);
                }
            }

            return $passes;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>|array<string, mixed>  $holderAttributes
     * @return array<string, mixed>
     */
    private function resolveHolderAttributesForPass(array $holderAttributes, int $index): array
    {
        if ($holderAttributes === []) {
            return [];
        }

        if (array_is_list($holderAttributes)) {
            $attributes = $holderAttributes[$index] ?? null;

            return is_array($attributes) ? $attributes : [];
        }

        return $holderAttributes;
    }

    /** @param array<string, mixed> $holderAttributes */
    private function createHolder(Pass $pass, array $holderAttributes): PassHolder
    {
        HolderAttributesValidator::validateAttributes($holderAttributes);

        $holder = new PassHolder;
        $holder->pass_id = $pass->getKey();
        $holder->name = $holderAttributes['name'] ?? null;
        $holder->email = $holderAttributes['email'] ?? null;

        if (isset($holderAttributes['holder_type'], $holderAttributes['holder_id'])) {
            $holder->holder_type = $holderAttributes['holder_type'];
            $holder->holder_id = $holderAttributes['holder_id'];
        }

        $holder->owner_type = $pass->owner_type;
        $holder->owner_id = $pass->owner_id;
        $holder->is_current = true;
        $holder->save();

        return $holder;
    }
}
