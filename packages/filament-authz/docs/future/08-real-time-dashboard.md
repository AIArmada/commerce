# Future: Real-Time Authorization Dashboard

> **Live monitoring of permission checks across your application**

## Overview

A dedicated dashboard for real-time visibility into authorization events, including permission checks, denials, anomalies, and role usage patterns.

## Dashboard Components

### 1. Live Permission Check Stream

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ 🔴 LIVE AUTHORIZATION STREAM                              [Pause] [Clear]   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│ 12:45:32  ✓ ALLOWED  john@example.com           post.update    Post #123   │
│ 12:45:31  ✓ ALLOWED  jane@example.com           order.view     Order #456  │
│ 12:45:30  ✗ DENIED   guest@example.com          user.delete    User #789   │
│ 12:45:29  ✓ ALLOWED  admin@example.com          settings.view  —           │
│ 12:45:28  ⚠ ELEVATED super@example.com          role.create    —           │
│ 12:45:27  ✓ ALLOWED  john@example.com           post.view      Post #456   │
│                                                                              │
│ [Show only denials] [Filter by user] [Filter by permission]                 │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 2. Implementation

```php
namespace AIArmada\FilamentPermissions\Pages;

class AuthorizationDashboardPage extends Page implements HasWebsocketConnection
{
    protected static string $view = 'filament-authz::pages.authorization-dashboard';
    
    public bool $isLive = true;
    public array $filters = [];
    public array $recentChecks = [];
    public array $stats = [];
    
    protected $listeners = [
        'echo:permissions,AuthorizationEvent' => 'handleAuthorizationEvent',
    ];
    
    public function mount(): void
    {
        $this->loadRecentChecks();
        $this->calculateStats();
    }
    
    public function handleAuthorizationEvent(array $event): void
    {
        if (!$this->isLive) {
            return;
        }
        
        if ($this->passesFilters($event)) {
            array_unshift($this->recentChecks, $event);
            $this->recentChecks = array_slice($this->recentChecks, 0, 100);
        }
        
        $this->updateStats($event);
    }
    
    public function loadRecentChecks(): void
    {
        $this->recentChecks = PermissionAuditLog::query()
            ->where('event_type', AuditEventType::PermissionChecked)
            ->when($this->filters['user'] ?? null, fn ($q, $user) => $q->where('user_id', $user))
            ->when($this->filters['permission'] ?? null, fn ($q, $perm) => $q->where('permission', $perm))
            ->when($this->filters['denied_only'] ?? false, fn ($q) => $q->where('result', false))
            ->latest()
            ->limit(100)
            ->get()
            ->toArray();
    }
    
    public function calculateStats(): void
    {
        $this->stats = Cache::remember('auth_dashboard_stats', 60, function () {
            $last24h = now()->subDay();
            
            return [
                'total_checks' => PermissionAuditLog::where('created_at', '>=', $last24h)->count(),
                'allowed' => PermissionAuditLog::where('created_at', '>=', $last24h)->where('result', true)->count(),
                'denied' => PermissionAuditLog::where('created_at', '>=', $last24h)->where('result', false)->count(),
                'unique_users' => PermissionAuditLog::where('created_at', '>=', $last24h)->distinct('user_id')->count(),
                'most_checked' => PermissionAuditLog::where('created_at', '>=', $last24h)
                    ->select('permission', DB::raw('count(*) as count'))
                    ->groupBy('permission')
                    ->orderByDesc('count')
                    ->limit(10)
                    ->get(),
                'most_denied' => PermissionAuditLog::where('created_at', '>=', $last24h)
                    ->where('result', false)
                    ->select('permission', DB::raw('count(*) as count'))
                    ->groupBy('permission')
                    ->orderByDesc('count')
                    ->limit(5)
                    ->get(),
                'by_hour' => $this->getHourlyBreakdown(),
            ];
        });
    }
    
    protected function getHourlyBreakdown(): array
    {
        return PermissionAuditLog::where('created_at', '>=', now()->subDay())
            ->select(
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('SUM(CASE WHEN result = 1 THEN 1 ELSE 0 END) as allowed'),
                DB::raw('SUM(CASE WHEN result = 0 THEN 1 ELSE 0 END) as denied')
            )
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->toArray();
    }
}
```

### 3. Stats Widgets

```php
class AuthorizationStatsWidget extends Widget
{
    protected static string $view = 'filament-authz::widgets.authorization-stats';
    
    public function getStats(): array
    {
        $last24h = now()->subDay();
        
        return [
            Stat::make('Permission Checks', number_format($this->getTotalChecks()))
                ->description('Last 24 hours')
                ->descriptionIcon('heroicon-o-clock')
                ->chart($this->getHourlyChart())
                ->color('gray'),
            
            Stat::make('Access Granted', number_format($this->getAllowedCount()))
                ->description($this->getAllowedPercentage() . '% allow rate')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success'),
            
            Stat::make('Access Denied', number_format($this->getDeniedCount()))
                ->description($this->getDeniedPercentage() . '% deny rate')
                ->descriptionIcon('heroicon-o-x-circle')
                ->color('danger'),
            
            Stat::make('Active Users', number_format($this->getActiveUsers()))
                ->description('Unique users with activity')
                ->descriptionIcon('heroicon-o-users')
                ->color('info'),
        ];
    }
}
```

### 4. Anomaly Detection Widget

```php
class AnomalyDetectionWidget extends Widget
{
    protected static string $view = 'filament-authz::widgets.anomaly-detection';
    
    public function getAnomalies(): Collection
    {
        return app(ComplianceReportService::class)->detectAnomalies([
            'window' => 24, // hours
            'thresholds' => [
                'denial_rate' => 0.3, // Alert if user has >30% denials
                'elevation_count' => 5, // Alert if user uses elevated permissions >5 times
                'off_hours_access' => true, // Alert for access outside business hours
                'new_permission_usage' => true, // Alert when user uses new permission
            ],
        ]);
    }
    
    public function renderAnomalies(): array
    {
        $anomalies = $this->getAnomalies();
        
        return [
            'high_denial_users' => $anomalies->filter(fn ($a) => $a['type'] === 'high_denial_rate'),
            'elevated_access' => $anomalies->filter(fn ($a) => $a['type'] === 'elevated_permission'),
            'off_hours' => $anomalies->filter(fn ($a) => $a['type'] === 'off_hours_access'),
            'unusual_activity' => $anomalies->filter(fn ($a) => $a['type'] === 'unusual_pattern'),
        ];
    }
}
```

### 5. Permission Usage Heatmap

```blade
{{-- resources/views/widgets/permission-heatmap.blade.php --}}
<x-filament::widget>
    <x-filament::card>
        <h3 class="text-lg font-medium mb-4">Permission Usage Heatmap (Last 7 Days)</h3>
        
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr>
                        <th class="text-left p-1">Permission</th>
                        @foreach($days as $day)
                            <th class="p-1 text-center">{{ $day->format('D') }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($permissions as $permission => $data)
                        <tr>
                            <td class="p-1 font-mono">{{ $permission }}</td>
                            @foreach($days as $day)
                                @php
                                    $count = $data[$day->format('Y-m-d')] ?? 0;
                                    $intensity = $this->getIntensity($count);
                                @endphp
                                <td class="p-1">
                                    <div 
                                        class="w-6 h-6 rounded {{ $intensity }}"
                                        title="{{ $count }} checks on {{ $day->format('M d') }}"
                                    ></div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        <div class="flex gap-2 mt-4 text-xs">
            <span>Less</span>
            <div class="w-4 h-4 rounded bg-green-100"></div>
            <div class="w-4 h-4 rounded bg-green-300"></div>
            <div class="w-4 h-4 rounded bg-green-500"></div>
            <div class="w-4 h-4 rounded bg-green-700"></div>
            <span>More</span>
        </div>
    </x-filament::card>
</x-filament::widget>
```

### 6. WebSocket Integration

```php
// Broadcasting authorization events
class AuthorizationEventBroadcaster
{
    public function __construct(protected AuditLogger $logger) {}
    
    public function logAndBroadcast(
        User $user,
        string $permission,
        bool $result,
        ?Model $resource = null
    ): void {
        $event = [
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'permission' => $permission,
            'result' => $result,
            'resource_type' => $resource ? get_class($resource) : null,
            'resource_id' => $resource?->id,
            'timestamp' => now()->toIso8601String(),
            'ip' => request()->ip(),
        ];
        
        // Log to database
        $this->logger->log(
            $result ? AuditEventType::PermissionGranted : AuditEventType::PermissionDenied,
            $event
        );
        
        // Broadcast to dashboard
        broadcast(new AuthorizationEvent($event))->toOthers();
    }
}

class AuthorizationEvent implements ShouldBroadcast
{
    public function __construct(public array $data) {}
    
    public function broadcastOn(): Channel
    {
        return new PrivateChannel('permissions');
    }
    
    public function broadcastAs(): string
    {
        return 'AuthorizationEvent';
    }
}
```

### 7. Configuration

```php
// config/filament-authz.php
return [
    'dashboard' => [
        'enabled' => true,
        
        // Real-time updates
        'realtime' => [
            'enabled' => true,
            'driver' => 'pusher', // pusher, redis, null
            'channel' => 'permissions',
        ],
        
        // Anomaly detection
        'anomaly_detection' => [
            'enabled' => true,
            'denial_threshold' => 0.3,
            'off_hours' => ['start' => '18:00', 'end' => '08:00'],
            'elevation_threshold' => 5,
        ],
        
        // Data retention for dashboard
        'retention' => [
            'detailed_logs' => 7, // days
            'aggregated_stats' => 90, // days
        ],
    ],
];
```

## Key Metrics

The dashboard provides:

1. **Real-time stream** — Live feed of all authorization events
2. **Allow/Deny rates** — Percentage of successful vs failed checks
3. **Top permissions** — Most frequently checked permissions
4. **Top denials** — Permissions most often resulting in denial
5. **User activity** — Active users and their permission usage
6. **Anomaly alerts** — Unusual patterns or suspicious activity
7. **Temporal patterns** — Usage by hour/day/week
8. **Heatmaps** — Visual representation of permission usage

## Shield Comparison

Shield provides no authorization monitoring dashboard. This feature gives administrators unprecedented visibility into how permissions are used across the application.
