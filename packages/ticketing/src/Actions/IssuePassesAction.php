<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Actions;

use AIArmada\Ticketing\Contracts\PassIssuerInterface;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\PassHolder;
use AIArmada\Ticketing\Support\PassIssuanceContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class IssuePassesAction
{
    /** @return Collection<int, Pass> */
    public function handle(PassIssuanceContext $context): Collection
    {
        return DB::transaction(function () use ($context) {
            $passes = app(PassIssuerInterface::class)->issuePassesFor($context);

            foreach ($passes as $pass) {
                if ($context->holderAttributes !== []) {
                    $this->createHolder($pass, $context->holderAttributes);
                }
            }

            return $passes;
        });
    }

    /** @param array<string, mixed> $holderAttributes */
    private function createHolder(Pass $pass, array $holderAttributes): PassHolder
    {
        $holder = new PassHolder;
        $holder->pass_id = $pass->getKey();
        $holder->name = $holderAttributes['name'] ?? null;
        $holder->email = $holderAttributes['email'] ?? null;

        if (isset($holderAttributes['holder_type'], $holderAttributes['holder_id'])) {
            $holder->holder_type = $holderAttributes['holder_type'];
            $holder->holder_id = $holderAttributes['holder_id'];
        }

        $holder->is_current = true;
        $holder->save();

        return $holder;
    }
}
