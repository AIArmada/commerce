<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

final class RevenueChart extends ChartWidget
{
    protected ?string $heading = 'Revenue (Last 30 Days)';

    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $owner = OwnerContext::resolve();

        $days = collect(range(29, 0))->map(fn (int $day) => Carbon::now()->subDays($day));

        if ($owner === null) {
            $revenues = $days->map(fn () => 0);
        } else {
            $revenues = $days->map(function (Carbon $date) use ($owner) {
                return Order::query()
                    ->forOwner($owner)
                    ->whereDate('created_at', $date)
                    ->whereNotNull('paid_at')
                    ->sum('grand_total') / 100;
            });
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (RM)',
                    'data' => $revenues->toArray(),
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $days->map(fn (Carbon $date) => $date->format('M d'))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
