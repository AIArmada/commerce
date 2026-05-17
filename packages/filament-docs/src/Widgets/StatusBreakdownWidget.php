<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Widgets;

use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\States\Cancelled;
use AIArmada\Docs\States\DocStatus;
use AIArmada\Docs\States\Draft;
use AIArmada\Docs\States\Overdue;
use AIArmada\Docs\States\Paid;
use AIArmada\Docs\States\PartiallyPaid;
use AIArmada\Docs\States\Pending;
use AIArmada\Docs\States\Refunded;
use AIArmada\Docs\States\Sent;
use AIArmada\FilamentDocs\Support\DocsOwnerScope;
use Filament\Widgets\ChartWidget;

final class StatusBreakdownWidget extends ChartWidget
{
    protected ?string $heading = 'Document Status Breakdown';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $docs = DocsOwnerScope::applyToDocs(Doc::query());

        /** @var array<int, class-string<DocStatus>> $statuses */
        $statuses = [
            Draft::class,
            Pending::class,
            Sent::class,
            Paid::class,
            PartiallyPaid::class,
            Overdue::class,
            Cancelled::class,
            Refunded::class,
        ];

        $labels = [];
        $data = [];
        $colors = [];

        foreach ($statuses as $status) {
            $count = (clone $docs)->where('status', DocStatus::normalize($status))->count();
            if ($count > 0) {
                $labels[] = DocStatus::labelFor($status);
                $data[] = $count;
                $colors[] = $this->getColorHex(DocStatus::colorFor($status));
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Documents',
                    'data' => $data,
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'right',
                ],
            ],
        ];
    }

    private function getColorHex(string $color): string
    {
        return match ($color) {
            'gray' => '#6b7280',
            'warning' => '#f59e0b',
            'info' => '#3b82f6',
            'success' => '#10b981',
            'danger' => '#ef4444',
            'primary' => '#8b5cf6',
            default => '#6b7280',
        };
    }
}
