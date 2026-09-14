<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Services;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Ticketing\Contracts\PassIssuerInterface;
use AIArmada\Ticketing\Contracts\TicketableInterface;
use AIArmada\Ticketing\Events\PassIssued;
use AIArmada\Ticketing\Exceptions\IssuanceQuantityExceededException;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Support\PassIssuanceContext;
use AIArmada\Ticketing\Support\TicketingOwnerGuard;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class DefaultPassIssuer implements PassIssuerInterface
{
    private const int MAX_COLLISION_ATTEMPTS = 10;

    private const int INSERT_CHUNK_SIZE = 200;

    public function issuePassesFor(PassIssuanceContext $context): Collection
    {
        if ($context->quantity <= 0) {
            return new Collection;
        }

        $maxQuantity = (int) config('ticketing.issuance.max_quantity', 500);

        if ($context->quantity > $maxQuantity) {
            throw new IssuanceQuantityExceededException($maxQuantity, $context->quantity);
        }

        $ticketable = $context->ticketType->ticketable;
        $passNumbers = $this->generatePassNumbers($context->quantity);
        $passes = new Collection;

        foreach ($passNumbers as $passNumber) {
            $passes->push($this->makePass($context, $ticketable, $passNumber));
        }

        $attempts = 0;

        while (true) {
            if (++$attempts > self::MAX_COLLISION_ATTEMPTS) {
                throw new RuntimeException('Unable to issue passes: unique code generation did not converge.');
            }

            try {
                return DB::transaction(function () use ($context, $passes): Collection {
                    $this->assertHomogeneousBatch($passes);

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
                if (! $this->replaceAllCollisions($passes)) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * All passes in a batch are built from one context, so they share the
     * ticket type, registration, and owner. Assert that here so guarding the
     * first pass covers the whole batch without per-row relation queries.
     *
     * @param  Collection<int, Pass>  $passes
     */
    private function assertHomogeneousBatch(Collection $passes): void
    {
        /** @var Pass|null $first */
        $first = $passes->first();

        if (! $first instanceof Pass) {
            return;
        }

        $keys = ['ticket_type_id', 'ticketable_type', 'ticketable_id', 'registration_type', 'registration_id', 'owner_type', 'owner_id'];

        foreach ($passes as $pass) {
            foreach ($keys as $key) {
                if ((string) $pass->getAttribute($key) !== (string) $first->getAttribute($key)) {
                    throw new RuntimeException('Pass issuance batch mixes ticket types, registrations, or owners.');
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    private function generatePassNumbers(int $quantity): array
    {
        $passNumbers = [];
        $attempts = 0;

        while (true) {
            if (++$attempts > self::MAX_COLLISION_ATTEMPTS) {
                throw new RuntimeException('Unable to generate unique pass numbers.');
            }

            while (count($passNumbers) < $quantity) {
                $candidate = $this->generatePassNo();

                if (! in_array($candidate, $passNumbers, true)) {
                    $passNumbers[] = $candidate;
                }
            }

            $collisions = $this->findCollisions('pass_no', $passNumbers);

            if ($collisions === []) {
                return $passNumbers;
            }

            $passNumbers = $this->replaceCollidingValues($passNumbers, $collisions, fn (): string => $this->generatePassNo());
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
     * Bulk insert intentionally bypasses model events (creating/created
     * observers, casts on write). Owner scoping is enforced up front by
     * asserting a homogeneous batch and guarding the first pass, and
     * issuance is observable via the per-pass PassIssued events.
     *
     * @param  Collection<int, Pass>  $passes
     */
    private function insertPasses(Collection $passes): void
    {
        $attempts = 0;

        while (true) {
            if (++$attempts > self::MAX_COLLISION_ATTEMPTS) {
                throw new RuntimeException('Unable to issue passes: unique code generation did not converge.');
            }

            if ($this->replaceAllCollisions($passes)) {
                continue;
            }

            foreach ($passes->chunk(self::INSERT_CHUNK_SIZE) as $chunk) {
                Pass::query()->insert($chunk->map(
                    fn (Pass $pass): array => $pass->getAttributes(),
                )->all());
            }

            foreach ($passes as $pass) {
                $pass->exists = true;
                $pass->wasRecentlyCreated = true;
                $pass->syncOriginal();
            }

            return;
        }
    }

    /**
     * Regenerate colliding unique codes across the batch. Returns whether
     * any collision was found (and repaired).
     *
     * @param  Collection<int, Pass>  $passes
     */
    private function replaceAllCollisions(Collection $passes): bool
    {
        $repaired = false;
        $generators = [
            'pass_no' => fn (): string => $this->generatePassNo(),
            'qr_code' => fn (): string => (string) Str::uuid(),
            'barcode' => fn (): string => Str::random(16),
        ];

        foreach ($generators as $column => $generate) {
            $values = $passes->map(fn (Pass $pass): ?string => $pass->getAttribute($column))->filter()->values()->all();
            $collisions = $this->findCollisions($column, $values);

            if ($collisions === []) {
                continue;
            }

            $repaired = true;
            $reserved = array_fill_keys($values, true);

            foreach ($passes as $pass) {
                $current = $pass->getAttribute($column);

                if (! in_array($current, $collisions, true)) {
                    continue;
                }

                do {
                    $candidate = $generate();
                } while (isset($reserved[$candidate]));

                unset($reserved[$current]);
                $reserved[$candidate] = true;
                $pass->setAttribute($column, $candidate);
            }
        }

        return $repaired;
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function findCollisions(string $column, array $values): array
    {
        if ($values === []) {
            return [];
        }

        return Pass::query()
            ->withoutOwnerScope()
            ->whereIn($column, $values)
            ->pluck($column)
            ->all();
    }

    /**
     * @param  list<string>  $values
     * @param  list<string>  $collisions
     * @return list<string>
     */
    private function replaceCollidingValues(array $values, array $collisions, callable $generate): array
    {
        $reserved = array_fill_keys($values, true);

        foreach ($values as $index => $value) {
            if (! in_array($value, $collisions, true)) {
                continue;
            }

            do {
                $candidate = $generate();
            } while (isset($reserved[$candidate]));

            unset($reserved[$value]);
            $reserved[$candidate] = true;
            $values[$index] = $candidate;
        }

        return $values;
    }
}
