<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Numbering\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Contracts\DocumentSequence;
use AIArmada\Docs\Data\DocType;
use AIArmada\Docs\Enums\ResetFrequency;
use AIArmada\Docs\Features\Numbering\Models\DocSequence;
use AIArmada\Docs\Support\DocTypeKey;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Manages document sequence number generation with atomic operations.
 */
final readonly class SequenceManager implements DocumentSequence
{
    public function nextNumber(string $docType): string
    {
        return $this->generate($docType, config('docs.owner.enabled', false) ? OwnerContext::resolve() : null);
    }

    /**
     * Generate the next document number for a given type.
     *
     * @param  DocType|string  $docType  The document type
     * @param  Model|null  $owner  Optional owner for multi-tenant sequences
     */
    public function generate(DocType | string $docType, ?Model $owner = null): string
    {
        $type = $docType instanceof DocType ? $docType->value : $docType;

        return OwnerContext::withOwner($owner, function () use ($type, $owner): string {
            // Serialize first-use creation: the unique index cannot cover
            // owner-less rows on all drivers, so the lock closes the race.
            return Cache::lock($this->firstUseLockKey($type, $owner), 10)->block(5, function () use ($type, $owner): string {
                return DB::transaction(function () use ($type, $owner): string {
                    $sequence = $this->getActiveSequence($type, $owner);

                    if (! $sequence) {
                        $sequence = $this->createDefaultSequenceResilient($type, $owner);
                    }

                    return $this->advance($sequence);
                });
            });
        });
    }

    /**
     * Get the active sequence for a document type.
     */
    public function getActiveSequence(string $docType, ?Model $owner = null, bool $forUpdate = true): ?DocSequence
    {
        $query = DocSequence::query()
            ->where('doc_type', $docType)
            ->where('is_active', true)
            ->forOwner($owner, false);

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * Create a default sequence for a document type.
     */
    public function createDefaultSequence(string $docType, ?Model $owner = null): DocSequence
    {
        $typeKey = DocTypeKey::sanitize($docType);
        $typeConfig = $typeKey !== null ? config("docs.types.{$typeKey}", []) : [];
        $numberingConfig = $typeConfig['numbering'] ?? [];

        $prefix = $numberingConfig['prefix']
            ?? DocType::tryFrom($docType)?->defaultPrefix()
            ?? mb_strtoupper(mb_substr($docType, 0, 3));

        $data = [
            'name' => ucfirst(str_replace('_', ' ', $docType)) . ' Sequence',
            'doc_type' => $docType,
            'prefix' => $prefix,
            'format' => config('docs.numbering.format.default', '{PREFIX}-{YYMM}-{NUMBER}'),
            'reset_frequency' => ResetFrequency::Yearly,
            'start_number' => 1,
            'increment' => 1,
            'padding' => config('docs.numbering.format.suffix_length', 6),
            'is_active' => true,
        ];

        return OwnerContext::withOwner($owner, function () use ($data, $owner): DocSequence {
            $sequence = new DocSequence($data);

            if ($owner !== null) {
                $sequence->assignOwner($owner);
            }

            $sequence->save();

            return $sequence;
        });
    }

    /**
     * Preview what the next number would be without generating it.
     */
    public function preview(DocType | string $docType, ?Model $owner = null): string
    {
        $type = $docType instanceof DocType ? $docType->value : $docType;
        $sequence = $this->getActiveSequence($type, $owner, false);

        if (! $sequence) {
            // Create a temporary preview
            $prefix = DocType::tryFrom($type)?->defaultPrefix() ?? mb_strtoupper(mb_substr($type, 0, 3));
            $padding = config('docs.numbering.format.suffix_length', 6);
            $number = mb_str_pad('1', $padding, '0', STR_PAD_LEFT);

            return $prefix . '-' . CarbonImmutable::now()->format('ym') . '-' . $number;
        }

        return $sequence->previewNextNumber();
    }

    /**
     * Reserve a specific number for a sequence (useful for manual entry).
     */
    public function reserve(DocType | string $docType, int $number, ?Model $owner = null): bool
    {
        if ($number < 1) {
            throw new InvalidArgumentException('Reserved sequence number must be positive.');
        }

        $type = $docType instanceof DocType ? $docType->value : $docType;

        return OwnerContext::withOwner($owner, function () use ($type, $number, $owner): bool {
            return Cache::lock($this->firstUseLockKey($type, $owner), 10)->block(5, function () use ($type, $number, $owner): bool {
                return DB::transaction(function () use ($type, $number, $owner): bool {
                    $sequence = $this->getActiveSequence($type, $owner);

                    if (! $sequence) {
                        $sequence = $this->createDefaultSequenceResilient($type, $owner);
                    }

                    $periodKey = $sequence->getCurrentPeriodKey();

                    $sequenceNumber = $sequence->numbers()
                        ->where('period_key', $periodKey)
                        ->lockForUpdate()
                        ->first();

                    if (! $sequenceNumber) {
                        $sequence->numbers()->create([
                            'period_key' => $periodKey,
                            'last_number' => $number,
                        ]);

                        return true;
                    }

                    // Only update if the reserved number is higher
                    if ($number > $sequenceNumber->last_number) {
                        $sequenceNumber->update(['last_number' => $number]);
                    }

                    return true;
                });
            });
        });
    }

    private function createDefaultSequenceResilient(string $docType, ?Model $owner): DocSequence
    {
        try {
            return DB::transaction(fn (): DocSequence => $this->createDefaultSequence($docType, $owner));
        } catch (UniqueConstraintViolationException $exception) {
            $sequence = $this->getActiveSequence($docType, $owner);

            if (! $sequence instanceof DocSequence) {
                throw new RuntimeException("Unable to resolve a document sequence for type [{$docType}].", previous: $exception);
            }

            return $sequence;
        }
    }

    private function firstUseLockKey(string $docType, ?Model $owner): string
    {
        $ownerKey = $owner === null
            ? 'global'
            : $owner->getMorphClass() . ':' . (string) $owner->getKey();

        return "docs.sequence-first-use.{$docType}.{$ownerKey}";
    }

    private function advance(DocSequence $sequence): string
    {
        if ($sequence->start_number < 1 || $sequence->increment < 1) {
            throw new InvalidArgumentException('Sequence start and increment must be positive.');
        }

        $periodKey = $sequence->getCurrentPeriodKey();
        $number = $sequence->numbers()->where('period_key', $periodKey)->lockForUpdate()->first();

        if ($number === null) {
            $next = $sequence->start_number;
            $sequence->numbers()->create(['period_key' => $periodKey, 'last_number' => $next]);
        } else {
            if ($number->last_number > PHP_INT_MAX - $sequence->increment) {
                throw new RuntimeException('Document sequence exhausted the supported integer range.');
            }

            $next = $number->last_number + $sequence->increment;
            $number->update(['last_number' => $next]);
        }

        return $sequence->formatNumber($next);
    }

    /**
     * Parse a document number to extract components.
     *
     * @return array{prefix: string, period: string|null, number: int|null}
     */
    public function parse(string $docNumber): array
    {
        // Common patterns: PREFIX-YYMM-000001, PREFIX-000001, PREFIX/YY/000001
        $parts = preg_split('/[-\/]/', $docNumber);

        if (! $parts || count($parts) < 2) {
            return ['prefix' => $docNumber, 'period' => null, 'number' => null];
        }

        $prefix = $parts[0];
        $lastPart = $parts[count($parts) - 1];
        $number = is_numeric($lastPart) ? (int) $lastPart : null;

        $period = count($parts) > 2 ? $parts[1] : null;

        return [
            'prefix' => $prefix,
            'period' => $period,
            'number' => $number,
        ];
    }
}
