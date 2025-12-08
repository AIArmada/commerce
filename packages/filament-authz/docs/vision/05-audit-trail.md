# Audit Trail & Compliance

> **Document:** 5 of 10  
> **Package:** `aiarmada/filament-authz`  
> **Status:** Vision

---

## Overview

Comprehensive **audit logging** for all permission changes, role assignments, and access attempts—enabling compliance with SOC2, GDPR, and enterprise security requirements.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    AUDIT TRAIL SYSTEM                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐       │
│  │   ACTIONS    │───▶│   EVENTS     │───▶│   STORAGE    │       │
│  └──────────────┘    └──────────────┘    └──────────────┘       │
│        │                    │                    │               │
│        ▼                    ▼                    ▼               │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐       │
│  │ Permission   │    │ Audit Event  │    │ Database     │       │
│  │ Granted      │    │ Dispatcher   │    │ Log Table    │       │
│  ├──────────────┤    ├──────────────┤    ├──────────────┤       │
│  │ Permission   │    │ Queue Worker │    │ Elasticsearch│       │
│  │ Revoked      │    │              │    │ (optional)   │       │
│  ├──────────────┤    └──────────────┘    ├──────────────┤       │
│  │ Role         │                        │ S3 Archive   │       │
│  │ Assigned     │                        │ (long-term)  │       │
│  ├──────────────┤                        └──────────────┘       │
│  │ Role         │                                               │
│  │ Removed      │                                               │
│  ├──────────────┤                                               │
│  │ Access       │                                               │
│  │ Attempt      │                                               │
│  └──────────────┘                                               │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Audit Event Types

### AuditEventType Enum

```php
enum AuditEventType: string
{
    // Permission Events
    case PermissionGranted = 'permission.granted';
    case PermissionRevoked = 'permission.revoked';
    case PermissionCreated = 'permission.created';
    case PermissionDeleted = 'permission.deleted';
    case PermissionUpdated = 'permission.updated';

    // Role Events
    case RoleAssigned = 'role.assigned';
    case RoleRemoved = 'role.removed';
    case RoleCreated = 'role.created';
    case RoleDeleted = 'role.deleted';
    case RoleUpdated = 'role.updated';

    // Access Events
    case AccessGranted = 'access.granted';
    case AccessDenied = 'access.denied';
    case AccessAttempt = 'access.attempt';

    // Impersonation Events
    case ImpersonationStarted = 'impersonation.started';
    case ImpersonationEnded = 'impersonation.ended';

    // System Events
    case PermissionsSynced = 'system.permissions_synced';
    case RolesImported = 'system.roles_imported';
    case BulkOperation = 'system.bulk_operation';

    public function severity(): AuditSeverity
    {
        return match ($this) {
            self::PermissionDeleted, self::RoleDeleted => AuditSeverity::Critical,
            self::PermissionGranted, self::RoleAssigned => AuditSeverity::High,
            self::AccessDenied, self::ImpersonationStarted => AuditSeverity::High,
            self::PermissionRevoked, self::RoleRemoved => AuditSeverity::Medium,
            default => AuditSeverity::Low,
        };
    }
}

enum AuditSeverity: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
}
```

---

## Permission Audit Log Model

### PermissionAuditLog

```php
final class PermissionAuditLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'event_type',
        'severity',
        'actor_id',
        'actor_type',
        'subject_id',
        'subject_type',
        'target_id',
        'target_type',
        'target_name',
        'old_value',
        'new_value',
        'context',
        'ip_address',
        'user_agent',
        'session_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => AuditEventType::class,
            'severity' => AuditSeverity::class,
            'old_value' => 'array',
            'new_value' => 'array',
            'context' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return config('filament-authz.database.tables.audit_logs', 'permission_audit_logs');
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to high-severity events.
     */
    public function scopeHighSeverity(Builder $query): Builder
    {
        return $query->whereIn('severity', [
            AuditSeverity::Critical,
            AuditSeverity::High,
        ]);
    }

    /**
     * Scope to specific actor.
     */
    public function scopeByActor(Builder $query, User $actor): Builder
    {
        return $query
            ->where('actor_type', User::class)
            ->where('actor_id', $actor->id);
    }

    /**
     * Scope to specific subject (e.g., affected user).
     */
    public function scopeForSubject(Builder $query, Model $subject): Builder
    {
        return $query
            ->where('subject_type', get_class($subject))
            ->where('subject_id', $subject->getKey());
    }

    /**
     * Scope to date range.
     */
    public function scopeBetweenDates(Builder $query, DateTimeInterface $start, DateTimeInterface $end): Builder
    {
        return $query->whereBetween('occurred_at', [$start, $end]);
    }
}
```

---

## Audit Logger Service

### AuditLogger

```php
final class AuditLogger
{
    public function __construct(
        private readonly bool $async = true,
    ) {}

    /**
     * Log a permission event.
     */
    public function log(
        AuditEventType $eventType,
        ?Model $actor = null,
        ?Model $subject = null,
        ?Model $target = null,
        array $context = [],
        mixed $oldValue = null,
        mixed $newValue = null
    ): void {
        $data = [
            'event_type' => $eventType,
            'severity' => $eventType->severity(),
            'actor_id' => $actor?->getKey(),
            'actor_type' => $actor ? get_class($actor) : null,
            'subject_id' => $subject?->getKey(),
            'subject_type' => $subject ? get_class($subject) : null,
            'target_id' => $target?->getKey(),
            'target_type' => $target ? get_class($target) : null,
            'target_name' => $target?->name ?? $target?->title ?? null,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'context' => array_merge($context, $this->captureRequestContext()),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'session_id' => session()?->getId(),
            'occurred_at' => now(),
        ];

        if ($this->async) {
            dispatch(new WriteAuditLogJob($data));
        } else {
            PermissionAuditLog::create($data);
        }
    }

    /**
     * Log permission granted.
     */
    public function permissionGranted(User $user, Permission $permission, ?User $actor = null): void
    {
        $this->log(
            eventType: AuditEventType::PermissionGranted,
            actor: $actor ?? auth()->user(),
            subject: $user,
            target: $permission,
            newValue: ['permission' => $permission->name],
        );
    }

    /**
     * Log permission revoked.
     */
    public function permissionRevoked(User $user, Permission $permission, ?User $actor = null): void
    {
        $this->log(
            eventType: AuditEventType::PermissionRevoked,
            actor: $actor ?? auth()->user(),
            subject: $user,
            target: $permission,
            oldValue: ['permission' => $permission->name],
        );
    }

    /**
     * Log role assigned.
     */
    public function roleAssigned(User $user, Role $role, ?User $actor = null): void
    {
        $this->log(
            eventType: AuditEventType::RoleAssigned,
            actor: $actor ?? auth()->user(),
            subject: $user,
            target: $role,
            newValue: ['role' => $role->name],
        );
    }

    /**
     * Log role removed.
     */
    public function roleRemoved(User $user, Role $role, ?User $actor = null): void
    {
        $this->log(
            eventType: AuditEventType::RoleRemoved,
            actor: $actor ?? auth()->user(),
            subject: $user,
            target: $role,
            oldValue: ['role' => $role->name],
        );
    }

    /**
     * Log access denied.
     */
    public function accessDenied(User $user, string $permission, array $context = []): void
    {
        $this->log(
            eventType: AuditEventType::AccessDenied,
            actor: $user,
            subject: $user,
            context: array_merge($context, ['attempted_permission' => $permission]),
        );
    }

    /**
     * Log impersonation started.
     */
    public function impersonationStarted(User $impersonator, User $impersonated): void
    {
        $this->log(
            eventType: AuditEventType::ImpersonationStarted,
            actor: $impersonator,
            subject: $impersonated,
            context: ['impersonator_roles' => $impersonator->getRoleNames()->toArray()],
        );
    }

    private function captureRequestContext(): array
    {
        return [
            'url' => request()?->fullUrl(),
            'method' => request()?->method(),
            'route' => request()?->route()?->getName(),
        ];
    }
}
```

---

## Event Subscribers

### PermissionEventSubscriber

```php
final class PermissionEventSubscriber
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function subscribe(Dispatcher $events): array
    {
        return [
            'Spatie\Permission\Events\PermissionCreated' => 'onPermissionCreated',
            'Spatie\Permission\Events\PermissionDeleted' => 'onPermissionDeleted',
            'Spatie\Permission\Events\RoleCreated' => 'onRoleCreated',
            'Spatie\Permission\Events\RoleDeleted' => 'onRoleDeleted',
            PermissionGrantedEvent::class => 'onPermissionGranted',
            PermissionRevokedEvent::class => 'onPermissionRevoked',
            RoleAssignedEvent::class => 'onRoleAssigned',
            RoleRemovedEvent::class => 'onRoleRemoved',
        ];
    }

    public function onPermissionCreated($event): void
    {
        $this->auditLogger->log(
            eventType: AuditEventType::PermissionCreated,
            actor: auth()->user(),
            target: $event->permission,
            newValue: $event->permission->toArray(),
        );
    }

    public function onPermissionGranted(PermissionGrantedEvent $event): void
    {
        $this->auditLogger->permissionGranted(
            $event->user,
            $event->permission,
            $event->actor,
        );
    }

    public function onRoleAssigned(RoleAssignedEvent $event): void
    {
        $this->auditLogger->roleAssigned(
            $event->user,
            $event->role,
            $event->actor,
        );
    }

    // Additional handlers...
}
```

---

## Compliance Reporting

### ComplianceReportService

```php
final class ComplianceReportService
{
    /**
     * Generate SOC2 access review report.
     */
    public function generateAccessReviewReport(DateTimeInterface $start, DateTimeInterface $end): array
    {
        $logs = PermissionAuditLog::query()
            ->betweenDates($start, $end)
            ->whereIn('event_type', [
                AuditEventType::PermissionGranted,
                AuditEventType::PermissionRevoked,
                AuditEventType::RoleAssigned,
                AuditEventType::RoleRemoved,
            ])
            ->with(['actor', 'subject', 'target'])
            ->get();

        return [
            'report_type' => 'SOC2 Access Review',
            'period' => [
                'start' => $start->toISOString(),
                'end' => $end->toISOString(),
            ],
            'summary' => [
                'total_changes' => $logs->count(),
                'permissions_granted' => $logs->where('event_type', AuditEventType::PermissionGranted)->count(),
                'permissions_revoked' => $logs->where('event_type', AuditEventType::PermissionRevoked)->count(),
                'roles_assigned' => $logs->where('event_type', AuditEventType::RoleAssigned)->count(),
                'roles_removed' => $logs->where('event_type', AuditEventType::RoleRemoved)->count(),
            ],
            'details' => $logs->map(fn ($log) => [
                'timestamp' => $log->occurred_at->toISOString(),
                'event' => $log->event_type->value,
                'actor' => $log->actor?->email ?? 'System',
                'subject' => $log->subject?->email ?? 'N/A',
                'target' => $log->target_name,
                'ip_address' => $log->ip_address,
            ])->toArray(),
        ];
    }

    /**
     * Generate privileged access report.
     */
    public function generatePrivilegedAccessReport(): array
    {
        $privilegedRoles = Role::query()
            ->whereIn('name', config('filament-authz.audit.privileged_roles', ['super_admin', 'admin']))
            ->with('users')
            ->get();

        return [
            'report_type' => 'Privileged Access Report',
            'generated_at' => now()->toISOString(),
            'roles' => $privilegedRoles->map(fn ($role) => [
                'role' => $role->name,
                'users_count' => $role->users->count(),
                'users' => $role->users->map(fn ($user) => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'assigned_at' => $this->getRoleAssignmentDate($user, $role),
                ])->toArray(),
            ])->toArray(),
        ];
    }

    /**
     * Generate failed access attempts report.
     */
    public function generateFailedAccessReport(DateTimeInterface $start, DateTimeInterface $end): array
    {
        $logs = PermissionAuditLog::query()
            ->betweenDates($start, $end)
            ->where('event_type', AuditEventType::AccessDenied)
            ->get();

        $byUser = $logs->groupBy('actor_id');
        $byPermission = $logs->groupBy(fn ($log) => $log->context['attempted_permission'] ?? 'unknown');

        return [
            'report_type' => 'Failed Access Attempts',
            'period' => [
                'start' => $start->toISOString(),
                'end' => $end->toISOString(),
            ],
            'summary' => [
                'total_attempts' => $logs->count(),
                'unique_users' => $byUser->count(),
                'unique_permissions' => $byPermission->count(),
            ],
            'top_users' => $byUser
                ->map(fn ($logs, $userId) => ['user_id' => $userId, 'attempts' => $logs->count()])
                ->sortByDesc('attempts')
                ->take(10)
                ->values()
                ->toArray(),
            'top_permissions' => $byPermission
                ->map(fn ($logs, $perm) => ['permission' => $perm, 'attempts' => $logs->count()])
                ->sortByDesc('attempts')
                ->take(10)
                ->values()
                ->toArray(),
        ];
    }

    private function getRoleAssignmentDate(User $user, Role $role): ?string
    {
        $log = PermissionAuditLog::query()
            ->where('event_type', AuditEventType::RoleAssigned)
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('target_type', Role::class)
            ->where('target_id', $role->id)
            ->latest('occurred_at')
            ->first();

        return $log?->occurred_at?->toISOString();
    }
}
```

---

## Audit CLI Commands

### AuditReportCommand

```php
#[AsCommand(name: 'authz:audit-report')]
final class AuditReportCommand extends Command
{
    protected $signature = 'authz:audit-report 
        {--type=access-review : Report type (access-review, privileged, failed-access)}
        {--start= : Start date (Y-m-d)}
        {--end= : End date (Y-m-d)}
        {--format=json : Output format (json, csv)}
        {--output= : Output file path}';

    public function handle(ComplianceReportService $reportService): int
    {
        $type = $this->option('type');
        $start = $this->option('start') ? Carbon::parse($this->option('start')) : now()->subMonth();
        $end = $this->option('end') ? Carbon::parse($this->option('end')) : now();

        $report = match ($type) {
            'access-review' => $reportService->generateAccessReviewReport($start, $end),
            'privileged' => $reportService->generatePrivilegedAccessReport(),
            'failed-access' => $reportService->generateFailedAccessReport($start, $end),
            default => throw new InvalidArgumentException("Unknown report type: {$type}"),
        };

        $output = $this->formatReport($report, $this->option('format'));

        if ($path = $this->option('output')) {
            file_put_contents($path, $output);
            $this->info("Report saved to: {$path}");
        } else {
            $this->line($output);
        }

        return self::SUCCESS;
    }

    private function formatReport(array $report, string $format): string
    {
        return match ($format) {
            'json' => json_encode($report, JSON_PRETTY_PRINT),
            'csv' => $this->toCsv($report),
            default => json_encode($report, JSON_PRETTY_PRINT),
        };
    }

    private function toCsv(array $report): string
    {
        // CSV conversion logic
    }
}
```

---

## Retention & Archival

### AuditRetentionService

```php
final class AuditRetentionService
{
    /**
     * Archive old audit logs to cold storage.
     */
    public function archiveLogs(int $olderThanDays = 90): int
    {
        $cutoff = now()->subDays($olderThanDays);

        $logs = PermissionAuditLog::query()
            ->where('occurred_at', '<', $cutoff)
            ->get();

        if ($logs->isEmpty()) {
            return 0;
        }

        // Archive to S3 or cold storage
        $archivePath = sprintf(
            'audit-archives/%s-%s.json.gz',
            $cutoff->format('Y-m-d'),
            now()->format('Y-m-d-His')
        );

        Storage::disk('s3')->put(
            $archivePath,
            gzencode(json_encode($logs->toArray()))
        );

        // Delete archived records
        PermissionAuditLog::query()
            ->whereIn('id', $logs->pluck('id'))
            ->delete();

        return $logs->count();
    }

    /**
     * Purge very old archives per GDPR requirements.
     */
    public function purgeOldArchives(int $olderThanYears = 7): int
    {
        $cutoff = now()->subYears($olderThanYears)->format('Y-m-d');
        $deleted = 0;

        $files = Storage::disk('s3')->files('audit-archives');

        foreach ($files as $file) {
            if (str_starts_with(basename($file), $cutoff) || basename($file) < $cutoff) {
                Storage::disk('s3')->delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }
}
```

---

## Navigation

**Previous:** [04-contextual-permissions.md](04-contextual-permissions.md)  
**Next:** [06-policy-evolution.md](06-policy-evolution.md)
