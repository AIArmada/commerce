<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Services;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Ticketing\Contracts\PassIssuerInterface;
use AIArmada\Ticketing\Contracts\TicketableInterface;
use AIArmada\Ticketing\Events\PassIssued;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Support\PassIssuanceContext;
use AIArmada\Ticketing\Support\TicketingOwnerGuard;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DefaultPassIssuer implements PassIssuerInterface
{
    public function issuePassesFor(PassIssuanceContext $context): Collection
    {
        if ($context->quantity <= 0) {
            return new Collection;
        }

        $ticketable = $context->ticketType->ticketable;
        $passNumbers = $this->generatePassNumbers($context->quantity);
        $passes = new Collection;

        foreach ($passNumbers as $passNumber) {
            $passes->push($this->makePass($context, $ticketable, $passNumber));
        }

        while (true) {
            try {
                return DB::transaction(function () use ($context, $passes): Collection {
                    /** @var Pass $firstPass */
                    $firstPass = $passes->first();
                    TicketingOwnerGuard::assertRelations($firstPass, [
                        ['relation' => 'ticketType'],
                        ['relation' => 'ticketable', 'required' => true],
                        ['relation' => 'registration'],
                    ]);

                    $this->insertPasses($passes);

                    foreach ($passes as $pass) {
                        DB::afterCommit(function () use ($pass, $context): void {
                            event(new PassIssued($pass, $context->issuedAt));
                        });
                    }

                    return $passes;
                });
            } catch (QueryException $exception) {
                $collisions = $this->findPassNumberCollisions($passes->pluck('pass_no')->all());

                if ($collisions === []) {
                    throw $exception;
                }

                $this->replaceCollidingPassNumbers($passes, $collisions);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function generatePassNumbers(int $quantity): array
    {
        $passNumbers = [];

        while (true) {
            while (count($passNumbers) < $quantity) {
                $candidate = $this->generatePassNo();

                if (! in_array($candidate, $passNumbers, true)) {
                    $passNumbers[] = $candidate;
                }
            }

            $collisions = $this->findPassNumberCollisions($passNumbers);

            if ($collisions === []) {
                return $passNumbers;
            }

            $reserved = array_fill_keys($passNumbers, true);

            foreach ($passNumbers as $index => $passNumber) {
                if (! in_array($passNumber, $collisions, true)) {
                    continue;
                }

                do {
                    $candidate = $this->generatePassNo();
                } while (isset($reserved[$candidate]));

                unset($reserved[$passNumber]);
                $reserved[$candidate] = true;
                $passNumbers[$index] = $candidate;
            }
        }
    }

    private function generatePassNo(): string
    {
        return (string) config('ticketing.defaults.pass_no_prefix', 'PASS-')
            . mb_strtoupper(Str::random(8));
    }

    private function makePass(
        PassIssuanceContext $context,
        ?Model $ticketable,
        string $passNumber,
    ): Pass {
        $pass = new Pass;
        $pass->setUniqueIds();
        $pass->ticketable_type = $ticketable?->getMorphClass() ?? $context->ticketType->ticketable_type;
        $pass->ticketable_id = $ticketable?->getKey() ?? $context->ticketType->ticketable_id;
        $pass->ticket_type_id = $context->ticketType->getKey();
        $pass->registration_type = $context->registrationType;
        $pass->registration_id = $context->registrationId;
        $pass->occurrence_id = $context->occurrenceId;
        $pass->session_id = $context->sessionId;
        $pass->pass_no = $passNumber;
        $pass->qr_code = (string) Str::uuid();
        $pass->barcode = Str::random(16);
        $pass->status = 'issued';
        $pass->issued_at = $context->issuedAt;
        $pass->metadata = $context->metadata;
        $pass->created_at = Carbon::instance($context->issuedAt);
        $pass->updated_at = Carbon::instance($context->issuedAt);

        $owner = OwnerContext::resolve();

        if ($owner !== null && config('ticketing.owner.auto_assign_on_create', true)) {
            $pass->assignOwner($owner);
        }

        if ($ticketable instanceof TicketableInterface) {
            $pass->transfer_expires_at = $ticketable->transferWindowEndsAt();
        }

        return $pass;
    }

    /**
     * @param  Collection<int, Pass>  $passes
     */
    private function insertPasses(Collection $passes): void
    {
        while (true) {
            $passNumbers = $passes->pluck('pass_no')->all();
            $collisions = $this->findPassNumberCollisions($passNumbers);

            if ($collisions !== []) {
                $this->replaceCollidingPassNumbers($passes, $collisions);

                continue;
            }

            Pass::query()->insert($passes->map(
                fn (Pass $pass): array => $pass->getAttributes(),
            )->all());

            foreach ($passes as $pass) {
                $pass->exists = true;
                $pass->wasRecentlyCreated = true;
                $pass->syncOriginal();
            }

            return;
        }
    }

    /**
     * @param  list<string>  $passNumbers
     * @return list<string>
     */
    private function findPassNumberCollisions(array $passNumbers): array
    {
        return Pass::query()
            ->withoutOwnerScope()
            ->whereIn('pass_no', $passNumbers)
            ->pluck('pass_no')
            ->all();
    }

    /**
     * @param  Collection<int, Pass>  $passes
     * @param  list<string>  $collisions
     */
    private function replaceCollidingPassNumbers(Collection $passes, array $collisions): void
    {
        $reserved = array_fill_keys($passes->pluck('pass_no')->all(), true);

        foreach ($passes as $pass) {
            if (! in_array($pass->pass_no, $collisions, true)) {
                continue;
            }

            do {
                $candidate = $this->generatePassNo();
            } while (isset($reserved[$candidate]));

            unset($reserved[$pass->pass_no]);
            $reserved[$candidate] = true;
            $pass->pass_no = $candidate;
        }
    }
}
