<?php

declare(strict_types=1);

namespace AIArmada\Addressing\ReferenceSync;

use AIArmada\Addressing\Support\AddressingTableResolver;
use Illuminate\Support\Facades\DB;

/** Maps reference tables to portable records; never captures customer addresses or ownership data. */
final class ReferenceTypes
{
    public const TYPES = [
        'countries', 'states', 'cities', 'areas', 'area_names', 'area_roles',
        'area_relationships', 'area_state_links', 'postal_codes', 'area_postal_codes',
    ];

    /**
     * Invokes $consume(entity type, natural key array, portable payload array) in bounded DB chunks.
     * All relations use canonical source keys rather than application UUIDs.
     */
    public function each(callable $consume, int $size = 1000): void
    {
        foreach (self::TYPES as $type) {
            $this->scan($type, $consume, $size);
        }
    }

    private function scan(string $type, callable $consume, int $size): void
    {
        $t = static fn (string $name): string => AddressingTableResolver::resolve($name);
        $query = DB::table($t($type) . ' as r')->select('r.*');

        if ($type === 'states' || $type === 'cities') {
            $query->leftJoin($t('countries') . ' as co', 'co.id', '=', 'r.country_id')
                ->addSelect('co.iso2 as resolved_country');
        }
        if ($type === 'cities') {
            $query->leftJoin($t('states') . ' as st', 'st.id', '=', 'r.state_id')
                ->addSelect('st.code as resolved_state_code', 'st.name as resolved_state_name');
        }
        if (in_array($type, ['area_names', 'area_roles', 'area_state_links', 'area_postal_codes'], true)) {
            $query->join($t('areas') . ' as ar', 'ar.id', '=', 'r.address_area_id')
                ->addSelect('ar.country_code as area_country', 'ar.source as area_source', 'ar.source_id as area_source_id');
        }
        if ($type === 'area_relationships') {
            $query->join($t('areas') . ' as ap', 'ap.id', '=', 'r.parent_address_area_id')
                ->join($t('areas') . ' as ac', 'ac.id', '=', 'r.child_address_area_id')
                ->addSelect('ap.country_code as parent_country', 'ap.source as parent_source', 'ap.source_id as parent_source_id')
                ->addSelect('ac.country_code as child_country', 'ac.source as child_source', 'ac.source_id as child_source_id');
        }
        if ($type === 'area_state_links') {
            $query->join($t('states') . ' as st', 'st.id', '=', 'r.state_id')
                ->addSelect('st.code as linked_state_code', 'st.name as linked_state_name');
        }
        if ($type === 'area_postal_codes') {
            $query->join($t('postal_codes') . ' as pc', 'pc.id', '=', 'r.postal_code_id')
                ->addSelect('pc.country_code as postal_country', 'pc.code as postal_code');
        }

        $query->orderBy('r.id')->chunkById($size, function ($rows) use ($type, $consume): void {
            foreach ($rows as $row) {
                $data = (array) $row;
                $key = $this->naturalKey($type, $data);
                $payload = $this->portablePayload($type, $data);
                $consume($type, $key, $payload);
            }
        }, 'r.id', 'id');
    }

    private function naturalKey(string $type, array $r): array
    {
        $area = static fn (string $prefix): array => [
            (string) $r[$prefix . '_country'], (string) $r[$prefix . '_source'], (string) $r[$prefix . '_source_id'],
        ];
        return match ($type) {
            'countries' => [(string) $r['iso2']],
            'states' => [(string) ($r['resolved_country'] ?? $r['country_code']), (string) ($r['code'] ?: '@name:' . $r['name'])],
            'cities' => [(string) ($r['resolved_country'] ?? $r['country_code']), (string) ($r['resolved_state_code'] ?: '@name:' . ($r['resolved_state_name'] ?? '')), (string) $r['name']],
            'areas' => [(string) $r['country_code'], (string) $r['source'], (string) $r['source_id']],
            'area_names' => [...$area('area'), (string) $r['source'], (string) $r['name_type'], (string) $r['name']],
            'area_roles' => [...$area('area'), (string) $r['source'], (string) $r['role']],
            'area_relationships' => [...$area('parent'), ...$area('child'), (string) $r['source'], (string) $r['relationship_type'], (string) ($r['hierarchy_type'] ?? '')],
            'area_state_links' => [...$area('area'), (string) ($r['linked_state_code'] ?: '@name:' . $r['linked_state_name']), (string) ($r['hierarchy_type'] ?? '')],
            'postal_codes' => [(string) $r['country_code'], (string) $r['code']],
            'area_postal_codes' => [(string) $r['postal_country'], (string) $r['postal_code'], ...$area('area'), (string) $r['source'], (string) ($r['source_id'] ?? ''), (string) $r['relationship_type']],
            default => throw new \LogicException("Unsupported reference type: {$type}"),
        };
    }

    /** Reproject a locked model's raw attributes for a last-moment optimistic integrity check. */
    public function portableForRecord(string $type, array $attributes): array
    {
        return $this->portablePayload($type, $attributes);
    }

    private function portablePayload(string $type, array $r): array
    {
        foreach (['id', 'country_id', 'state_id', 'address_area_id', 'postal_code_id',
            'parent_address_area_id', 'child_address_area_id', 'parent_id', 'created_at',
            'updated_at', 'synced_at', 'slug'] as $remove) {
            unset($r[$remove]);
        }
        // Values added by joins are source keys, not target database identifiers.
        foreach (['resolved_country', 'resolved_state_code', 'resolved_state_name', 'area_country',
            'area_source', 'area_source_id', 'parent_country', 'parent_source', 'parent_source_id',
            'child_country', 'child_source', 'child_source_id', 'linked_state_code',
            'linked_state_name', 'postal_country', 'postal_code'] as $key) {
            unset($r[$key]);
        }
        foreach (['metadata', 'source_payload', 'translations'] as $json) {
            if (array_key_exists($json, $r) && is_string($r[$json])) {
                $r[$json] = Canonical::decode($r[$json]);
            }
        }
        foreach (['is_active', 'is_primary', 'is_preferred'] as $boolean) {
            if (array_key_exists($boolean, $r)) {
                $r[$boolean] = (bool) $r[$boolean];
            }
        }
        foreach (['latitude', 'longitude'] as $float) {
            if (isset($r[$float])) {
                $r[$float] = (float) $r[$float];
            }
        }
        // Source identifiers of referenced records become part of the portable payload.
        // The exact counterpart can be reconstructed using natural_key in the bundle.
        return $r;
    }
}
