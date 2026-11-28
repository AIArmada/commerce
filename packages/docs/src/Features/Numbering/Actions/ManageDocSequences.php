<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Numbering\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Docs\Contracts\DocumentSequenceSettings;
use AIArmada\Docs\Data\DocType;
use AIArmada\Docs\Enums\ResetFrequency;
use AIArmada\Docs\Features\Numbering\Models\DocSequence;
use AIArmada\Docs\Features\Numbering\Support\SequenceWriteLock;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class ManageDocSequences implements DocumentSequenceSettings
{
    public function create(array $data): string
    {
        $attributes = $this->validated($data);

        return $this->serialize(function () use ($attributes): string {
            $this->assertFormat($attributes);
            $this->assertAvailable($attributes['doc_type'], (bool) ($attributes['is_active'] ?? true));

            return (string) DocSequence::query()->create($attributes)->getKey();
        });
    }

    public function update(string $sequenceId, array $data): void
    {
        $attributes = $this->validated($data, partial: true);

        $this->serialize(function () use ($sequenceId, $attributes): void {
            $sequence = $this->lockSequence($sequenceId);
            $sequence->fill($attributes);
            $this->assertFormat($sequence->getAttributes());

            if ($sequence->isDirty(['doc_type', 'prefix', 'format', 'reset_frequency', 'start_number', 'increment', 'padding', 'is_active'])
                && $sequence->numbers()->withoutOwnerScope()->exists()) {
                throw ValidationException::withMessages(['sequence' => 'Numbering settings cannot change after a number has been issued or reserved.']);
            }

            $this->assertAvailable($sequence->doc_type->value, $sequence->is_active, $sequenceId);
            $sequence->save();
        });
    }

    public function remove(array $sequenceIds): void
    {
        $sequenceIds = array_values(array_unique($sequenceIds));
        sort($sequenceIds, SORT_STRING);

        $this->serialize(function () use ($sequenceIds): void {
            foreach ($sequenceIds as $sequenceId) {
                $sequence = $this->lockSequence($sequenceId);

                if ($sequence->numbers()->withoutOwnerScope()->exists()) {
                    throw ValidationException::withMessages(['sequence' => 'A sequence with issued or reserved numbers cannot be deleted.']);
                }

                $sequence->delete();
            }
        });
    }

    /** @template T
     * @param Closure(): T $callback
     * @return T
     */
    private function serialize(Closure $callback): mixed
    {
        $owner = config('docs.owner.enabled', false) ? OwnerContext::resolve() : null;

        return Cache::lock(SequenceWriteLock::key($owner), 60)->block(5, fn () => (new DocSequence)->getConnection()->transaction($callback));
    }

    private function lockSequence(string $sequenceId): DocSequence
    {
        if (config('docs.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(DocSequence::class, $sequenceId);
        }

        return DocSequence::query()->forOwner()->lockForUpdate()->findOrFail($sequenceId);
    }

    private function assertAvailable(string $docType, bool $active, ?string $except = null): void
    {
        if (! $active) {
            return;
        }

        $query = DocSequence::query()->forOwner()->where('doc_type', $docType)->where('is_active', true);

        if ($except !== null) {
            $query->whereKeyNot($except);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages(['is_active' => 'Only one active sequence is allowed for each document type and owner.']);
        }
    }

    /** @param array<string, mixed> $data */
    private function assertFormat(array $data): void
    {
        if (! str_contains((string) $data['format'], '{NUMBER}')) {
            throw ValidationException::withMessages(['format' => 'The number format must include {NUMBER}.']);
        }
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function validated(array $data, bool $partial = false): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];

        return Validator::make($data, [
            'name' => [...$required, 'string', 'max:255'],
            'doc_type' => [...$required, Rule::enum(DocType::class)],
            'prefix' => [...$required, 'string', 'max:20'],
            'format' => [...$required, 'string', 'max:255'],
            'reset_frequency' => [...$required, Rule::enum(ResetFrequency::class)],
            'start_number' => [...$required, 'integer', 'min:1', 'max:2147483647'],
            'increment' => [...$required, 'integer', 'min:1', 'max:2147483647'],
            'padding' => [...$required, 'integer', 'min:1', 'max:10'],
            'is_active' => ['sometimes', 'boolean'],
        ])->validate();
    }
}
