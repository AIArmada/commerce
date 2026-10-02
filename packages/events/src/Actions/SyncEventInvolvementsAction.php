<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventInvolvement;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRole;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Support\EventScopeResolver;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Replace the involvement rows owned by one event, occurrence, or session scope.
 *
 * Rows outside the given scope are never touched. Organizer rows are preserved
 * unless 'organizer' is explicitly included in the role filter, and rows may
 * only use roles inside the explicit filter. The scope is re-resolved from
 * persisted storage, so dirty in-memory attributes cannot redirect writes.
 */
final class SyncEventInvolvementsAction
{
    /**
     * @param  Event|EventOccurrence|EventSession  $scope  Scope that owns the synced rows.
     * @param  list<array{role_code: string, involveable_type?: ?string, involveable_id?: ?string, display_name?: ?string, visibility?: ?string, status?: ?string, notes?: ?string}>  $rows  Canonical role rows in display order.
     * @param  list<string>|null  $roleCodes  Roles managed by this sync; other roles are preserved. Null manages every non-organizer role.
     */
    public function handle(Event | EventOccurrence | EventSession $scope, array $rows, ?array $roleCodes = null): int
    {
        $resolved = EventScopeResolver::resolve($scope);
        $eventId = $resolved['event_id'];
        $occurrenceId = $resolved['occurrence_id'];
        $sessionId = $resolved['session_id'];

        $roleFilter = $roleCodes === null
            ? null
            : array_values(array_unique(array_filter($roleCodes, static fn (mixed $code): bool => is_string($code) && $code !== '')));

        if ($roleFilter === []) {
            throw new InvalidArgumentException('Managed role codes must not be empty; pass null to manage every non-organizer role.');
        }

        $validated = $this->validateRows($rows, $roleFilter);
        $roleIds = $this->resolveRoleIds($validated);

        return DB::transaction(function () use ($eventId, $occurrenceId, $sessionId, $validated, $roleFilter, $roleIds): int {
            $this->deleteScopedRows($eventId, $occurrenceId, $sessionId, $roleFilter);

            if ($validated === []) {
                return 0;
            }

            $synced = 0;

            foreach ($validated as $index => $row) {
                EventInvolvement::query()->create([
                    'event_id' => $eventId,
                    'event_occurrence_id' => $occurrenceId,
                    'event_session_id' => $sessionId,
                    'involveable_type' => $row['involveable_type'],
                    'involveable_id' => $row['involveable_id'],
                    'event_role_id' => $roleIds[$row['role_code']],
                    'role_code' => $row['role_code'],
                    'status' => $row['status'],
                    'visibility' => $row['visibility'],
                    'notes' => $row['notes'],
                    'display_name' => $row['display_name'],
                    'sort_order' => $index + 1,
                ]);

                $synced++;
            }

            return $synced;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>|null  $roleFilter
     * @return list<array{role_code: string, involveable_type: ?string, involveable_id: ?string, display_name: ?string, visibility: string, status: string, notes: ?string}>
     */
    private function validateRows(array $rows, ?array $roleFilter): array
    {
        $validated = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new InvalidArgumentException('Each involvement row must be an array.');
            }

            $roleCode = $row['role_code'] ?? null;

            if (! is_string($roleCode) || $roleCode === '') {
                throw new InvalidArgumentException('Each involvement row requires a role_code.');
            }

            if ($roleFilter !== null && ! in_array($roleCode, $roleFilter, true)) {
                throw new InvalidArgumentException(sprintf('Involvement role [%s] is outside the managed roles for this sync.', $roleCode));
            }

            if ($roleCode === 'organizer' && ($roleFilter === null || ! in_array('organizer', $roleFilter, true))) {
                throw new InvalidArgumentException("Involvement rows with the organizer role require 'organizer' in the managed roles.");
            }

            $involveableId = isset($row['involveable_id']) && is_string($row['involveable_id']) && $row['involveable_id'] !== ''
                ? $row['involveable_id']
                : null;
            $involveableType = $involveableId === null
                ? null
                : (isset($row['involveable_type']) && is_string($row['involveable_type']) && $row['involveable_type'] !== ''
                    ? $row['involveable_type']
                    : null);

            if ($involveableId !== null && $involveableType === null) {
                throw new InvalidArgumentException('Involvement rows with an involveable_id require an involveable_type.');
            }

            $displayName = isset($row['display_name']) && is_string($row['display_name']) && mb_trim($row['display_name']) !== ''
                ? mb_trim($row['display_name'])
                : null;

            if ($involveableId === null && $displayName === null) {
                throw new InvalidArgumentException('Each involvement row requires an involveable reference or a display_name.');
            }

            $visibility = $row['visibility'] ?? 'public';

            if (! in_array($visibility, ['public', 'private'], true)) {
                throw new InvalidArgumentException('Involvement visibility must be public or private.');
            }

            $status = $row['status'] ?? 'active';

            if (! is_string($status) || $status === '') {
                throw new InvalidArgumentException('Involvement status must be a non-empty string.');
            }

            $validated[] = [
                'role_code' => $roleCode,
                'involveable_type' => $involveableType,
                'involveable_id' => $involveableId,
                'display_name' => $displayName,
                'visibility' => $visibility,
                'status' => $status,
                'notes' => isset($row['notes']) && is_string($row['notes']) && mb_trim($row['notes']) !== ''
                    ? mb_trim($row['notes'])
                    : null,
            ];
        }

        return $this->deduplicateRows($validated);
    }

    /**
     * @param  list<array{role_code: string, involveable_type: ?string, involveable_id: ?string, display_name: ?string, visibility: string, status: string, notes: ?string}>  $rows
     * @return list<array{role_code: string, involveable_type: ?string, involveable_id: ?string, display_name: ?string, visibility: string, status: string, notes: ?string}>
     */
    private function deduplicateRows(array $rows): array
    {
        $seen = [];
        $deduplicated = [];

        foreach ($rows as $row) {
            $key = implode("\0", [
                $row['role_code'],
                $row['involveable_type'] ?? '',
                $row['involveable_id'] ?? '',
                $row['display_name'] ?? '',
            ]);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $deduplicated[] = $row;
        }

        return $deduplicated;
    }

    /**
     * @param  list<array{role_code: string, involveable_type: ?string, involveable_id: ?string, display_name: ?string, visibility: string, status: string, notes: ?string}>  $rows
     * @return array<string, string>
     */
    private function resolveRoleIds(array $rows): array
    {
        $roleCodes = array_values(array_unique(array_column($rows, 'role_code')));

        if ($roleCodes === []) {
            return [];
        }

        /** @var array<string, EventRole> $roles */
        $roles = EventRole::query()
            ->whereIn('code', $roleCodes)
            ->get(['id', 'code', 'is_active'])
            ->keyBy(static fn (EventRole $role): string => (string) $role->code)
            ->all();

        $unknown = array_values(array_diff($roleCodes, array_keys($roles)));

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown involvement role codes: ' . implode(', ', $unknown) . '.');
        }

        $inactive = [];

        foreach ($roleCodes as $code) {
            if (! $roles[$code]->is_active) {
                $inactive[] = $code;
            }
        }

        if ($inactive !== []) {
            throw new InvalidArgumentException('Inactive involvement role codes: ' . implode(', ', $inactive) . '.');
        }

        $roleIds = [];

        foreach ($roleCodes as $code) {
            $roleIds[$code] = (string) $roles[$code]->getKey();
        }

        return $roleIds;
    }

    /**
     * @param  list<string>|null  $roleFilter
     */
    private function deleteScopedRows(string $eventId, ?string $occurrenceId, ?string $sessionId, ?array $roleFilter): void
    {
        $query = EventInvolvement::query()
            ->where('event_id', $eventId);

        if ($occurrenceId === null) {
            $query->whereNull('event_occurrence_id');
        } else {
            $query->where('event_occurrence_id', $occurrenceId);
        }

        if ($sessionId === null) {
            $query->whereNull('event_session_id');
        } else {
            $query->where('event_session_id', $sessionId);
        }

        if ($roleFilter !== null) {
            $query->whereIn('role_code', $roleFilter);
        }

        if ($roleFilter === null || ! in_array('organizer', $roleFilter, true)) {
            $query->where('role_code', '!=', 'organizer');
        }

        $query->delete();
    }
}
