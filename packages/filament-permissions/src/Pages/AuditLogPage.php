<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Pages;

use AIArmada\FilamentPermissions\Enums\AuditEventType;
use AIArmada\FilamentPermissions\Models\PermissionAuditLog;
use AIArmada\FilamentPermissions\Services\ComplianceReportService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogPage extends Page
{
    public ?string $startDate = null;

    public ?string $endDate = null;

    public ?string $eventTypeFilter = null;

    public ?string $severityFilter = null;

    /** @var Collection<int, PermissionAuditLog> */
    public Collection $logs;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected string $view = 'filament-permissions::pages.audit-log';

    protected static ?string $title = 'Permission Audit Log';

    protected static ?string $navigationLabel = 'Audit Log';

    protected static ?int $navigationSort = 12;

    public static function getNavigationGroup(): ?string
    {
        return config('filament-permissions.navigation.group', 'Administration');
    }

    public function mount(): void
    {
        $this->startDate = now()->subDays(7)->toDateString();
        $this->endDate = now()->toDateString();
        $this->loadLogs();
    }

    public function loadLogs(): void
    {
        $query = PermissionAuditLog::query()
            ->whereBetween('created_at', [
                Carbon::parse($this->startDate)->startOfDay(),
                Carbon::parse($this->endDate)->endOfDay(),
            ])
            ->orderBy('created_at', 'desc');

        if ($this->eventTypeFilter !== null) {
            $query->where('event_type', $this->eventTypeFilter);
        }

        if ($this->severityFilter !== null) {
            $query->where('severity', $this->severityFilter);
        }

        $this->logs = $query->limit(500)->get();
    }

    public function filterByEventType(string $eventType): void
    {
        $this->eventTypeFilter = $eventType;
        $this->loadLogs();
    }

    public function filterBySeverity(string $severity): void
    {
        $this->severityFilter = $severity;
        $this->loadLogs();
    }

    public function clearFilters(): void
    {
        $this->eventTypeFilter = null;
        $this->severityFilter = null;
        $this->loadLogs();
    }

    public function exportCsv(): StreamedResponse
    {
        $service = app(ComplianceReportService::class);
        $csv = $service->exportToCsv(
            Carbon::parse($this->startDate),
            Carbon::parse($this->endDate)
        );

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, 'audit-log-'.now()->format('Y-m-d').'.csv');
    }

    /**
     * Get event type options for filter.
     *
     * @return array<string, string>
     */
    public function getEventTypeOptions(): array
    {
        $options = [];
        foreach (AuditEventType::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * Get statistics for the current filter.
     *
     * @return array{total: int, by_severity: array<string, int>, by_category: array<string, int>}
     */
    public function getStatistics(): array
    {
        $bySeverity = [];
        $byCategory = [];

        foreach ($this->logs as $log) {
            $severity = $log->getSeverityEnum()->value;
            $bySeverity[$severity] = ($bySeverity[$severity] ?? 0) + 1;

            $eventType = $log->getEventTypeEnum();
            if ($eventType !== null) {
                $category = $eventType->category();
                $byCategory[$category] = ($byCategory[$category] ?? 0) + 1;
            }
        }

        return [
            'total' => $this->logs->count(),
            'by_severity' => $bySeverity,
            'by_category' => $byCategory,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('filter')
                ->label('Filter')
                ->form([
                    DatePicker::make('start_date')
                        ->label('Start Date')
                        ->default($this->startDate),
                    DatePicker::make('end_date')
                        ->label('End Date')
                        ->default($this->endDate),
                ])
                ->action(function (array $data): void {
                    $this->startDate = $data['start_date'];
                    $this->endDate = $data['end_date'];
                    $this->loadLogs();
                }),
            Action::make('exportCsv')
                ->label('Export CSV')
                ->action(fn () => $this->exportCsv())
                ->icon('heroicon-o-arrow-down-tray'),
            Action::make('refresh')
                ->label('Refresh')
                ->action(fn () => $this->loadLogs())
                ->icon('heroicon-o-arrow-path'),
        ];
    }
}
