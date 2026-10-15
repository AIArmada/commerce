<?php

declare(strict_types=1);

namespace AIArmada\Addressing\ReferenceSync;

use AIArmada\Addressing\Support\ModelResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;

/**
 * Conservative inbound writer. Never hard-deletes core geography or rewrites customer addresses.
 * Relation changes and structurally ambiguous renames are exported for upstream review,
 * but require a dedicated topology migration before safe automatic downstream application.
 */
final class ReferenceWriter
{
    private const WRITABLE = ['countries', 'states', 'cities', 'areas', 'postal_codes'];

    /** Apply at most N approved safe changes. Idempotent; unresolved items remain queued. */
    public function applyApproved(int $limit = 100): array
    {
        if ($limit < 1 || $limit > 1000) {
            throw new \InvalidArgumentException('Batch limit must be between 1 and 1000.');
        }
        // Re-snapshot and reclassify immediately, invalidating stale approvals.
        (new SnapshotRepository)->captureLocal();
        (new DiffEngine)->rebuild();
        $result = ['applied' => 0, 'reconciled' => 0, 'deferred' => 0];
        $rows = DB::table(DiffEngine::TABLE)->where('direction', 'delivery')
            ->where('decision', 'approved')->orderBy('id')->limit($limit)->get();
        foreach ($rows as $change) {
            if (! in_array($change->operation, ['create', 'update', 'reconciled'], true)
                || ! in_array($change->entity_type, self::WRITABLE, true)) {
                $result['deferred']++;
                continue;
            }
            DB::transaction(function () use ($change, &$result): void {
                $locked = DB::table(DiffEngine::TABLE)->where('id', $change->id)->lockForUpdate()->first();
                if ($locked === null || $locked->fingerprint !== $change->fingerprint || $locked->decision !== 'approved') {
                    throw new RuntimeException('A reference update changed while it was being applied.');
                }
                if ($change->operation === 'reconciled') {
                    $this->assertCurrentMatches(
                        $change->entity_type, Canonical::decode($change->natural_key),
                        Canonical::decode($change->local_payload),
                    );
                    (new SnapshotRepository)->acceptRemote($change->entity_type, $change->key_hash);
                    $result['reconciled']++;
                } else {
                    $key = Canonical::decode($change->natural_key);
                    $payload = Canonical::decode($change->remote_payload);
                    if (! is_array($key)) {
                        throw new RuntimeException('Missing reference identity.');
                    }
                    $this->write($change->entity_type, $key, $payload,
                        Canonical::decode($change->local_payload), $change->operation);
                    (new SnapshotRepository)->acceptRemote($change->entity_type, $change->key_hash);
                    $result['applied']++;
                }
                DB::table(DiffEngine::TABLE)->where('id', $change->id)
                    ->update(['decision' => 'applied', 'updated_at' => now()]);
            });
        }
        (new SnapshotRepository)->captureLocal();
        (new DiffEngine)->rebuild();
        return $result;
    }

    private function assertCurrentMatches(string $type, array $key, ?array $expectedLocal): ?Model
    {
        $record = $this->lookup($type, $key);
        // Lock and reject writes that happened after the reviewer viewed the diff.
        if (($record === null) !== ($expectedLocal === null)) {
            throw new RuntimeException('Local reference presence changed during synchronization.');
        }
        if ($record instanceof Model && $expectedLocal !== null) {
            $current = (new ReferenceTypes)->portableForRecord($type, $record->getAttributes());
            if (! hash_equals(Canonical::checksum($expectedLocal), Canonical::checksum($current))) {
                throw new RuntimeException('Local reference content changed during synchronization. Recompare first.');
            }
        }
        return $record;
    }

    private function write(string $type, array $key, ?array $payload, ?array $expectedLocal, string $operation): void
    {
        $record = $this->assertCurrentMatches($type, $key, $expectedLocal);
        if ($operation === 'retire') {
            if (! $record instanceof Model || ! in_array($type, ['areas', 'postal_codes'], true)) {
                throw new LogicException('Retirement of this reference type needs manual impact review.');
            }
            $record->is_active = false;
            $record->saveOrFail();
            return;
        }
        if (! is_array($payload)) {
            throw new LogicException('Cannot apply a create/update without a canonical payload.');
        }
        $class = $this->modelClass($type);
        $record ??= new $class;
        $attrs = $this->mapAttributes($type, $key, $payload);
        $record->fill($attrs);
        $record->saveOrFail();
    }

    private function lookup(string $type, array $key): ?Model
    {
        $class = $this->modelClass($type);
        return match ($type) {
            'countries' => $class::query()->where('iso2', $key[0])->lockForUpdate()->first(),
            'states' => $this->state($key[0], $key[1], lock: true),
            'cities' => $class::query()->where('country_id', $this->countryId($key[0]))
                ->where('state_id', $this->stateId($key[0], $key[1]))
                ->where('name', $key[2])->lockForUpdate()->first(),
            'areas' => $class::query()->where('country_code', $key[0])->where('source', $key[1])
                ->where('source_id', $key[2])->lockForUpdate()->first(),
            'postal_codes' => $class::query()->where('country_code', $key[0])->where('code', $key[1])->lockForUpdate()->first(),
            default => null,
        };
    }

    private function mapAttributes(string $type, array $key, array $payload): array
    {
        $p = $payload;
        foreach (['id', 'created_at', 'updated_at', 'synced_at', 'country_id', 'state_id', 'parent_id',
            'address_area_id', 'postal_code_id'] as $remove) {
            unset($p[$remove]);
        }
        switch ($type) {
            case 'states':
                $p['country_id'] = $this->countryId($key[0]);
                break;
            case 'cities':
                $p['country_id'] = $this->countryId($key[0]);
                $p['state_id'] = $this->stateId($key[0], $key[1]);
                break;
            case 'areas':
                $p['country_id'] = $this->countryId($key[0]);
                $parent = $p['parent_source_id'] ?? null;
                if (is_string($parent) && $parent !== '') {
                    $class = ModelResolver::areaClass();
                    $matches = $class::query()->where('country_code', $key[0])
                        ->where('source_id', $parent)->limit(2)->get();
                    if ($matches->count() !== 1) {
                        throw new LogicException('Missing or ambiguous parent source ID; topology review required.');
                    }
                    $p['parent_id'] = $matches->first()->getKey();
                }
                // The source package owns these stable identifiers.
                $p['source'] = $key[1];
                $p['source_id'] = $key[2];
                $p['slug'] = Str::limit(Str::slug((string) ($p['name'] ?? '')), 255, '');
                if (array_key_exists('parent_source_id', $p) && ($p['parent_source_id'] === null || $p['parent_source_id'] === '')) {
                    $p['parent_id'] = null;
                }
                break;
        }
        return $p;
    }

    private function modelClass(string $type): string
    {
        return match ($type) {
            'countries' => ModelResolver::countryClass(),
            'states' => ModelResolver::stateClass(),
            'cities' => ModelResolver::cityClass(),
            'areas' => ModelResolver::areaClass(),
            'postal_codes' => ModelResolver::postalCodeClass(),
            default => throw new LogicException('Automatic application is disabled for this reference type.'),
        };
    }

    private function countryId(string $iso2): string
    {
        $class = ModelResolver::countryClass();
        return (string) $class::query()->where('iso2', $iso2)->firstOrFail()->getKey();
    }

    private function stateId(string $countryCode, string $key): ?string
    {
        if ($key === '@name:' || $key === '') {
            return null;
        }
        return $this->state($countryCode, $key)?->getKey();
    }

    private function state(string $countryCode, string $key, bool $lock = false): ?Model
    {
        $class = ModelResolver::stateClass();
        $query = $class::query()->where('country_id', $this->countryId($countryCode));
        if (str_starts_with($key, '@name:')) {
            $query->where('name', substr($key, 6));
        } else {
            $query->where('code', $key);
        }
        if ($lock) {
            $query->lockForUpdate();
        }
        return $query->first();
    }
}
