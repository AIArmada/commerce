<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Widgets;

use AIArmada\Chip\Models\SendInstruction;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

final class PayoutAmountWidget extends ChartWidget
{
    protected ?string $heading = 'Payout Volume';

    protected ?string $description = 'Daily payout amounts over the last 30 days';

    protected static ?int $sort = 11;

    protected ?string $maxHeight = '300px';

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $data = $this->getPayoutData();

        return [
            'datasets' => [
                [
                    'label' => 'Payouts (MYR)',
                    'data' => $data['amounts'],
                    'backgroundColor' => 'rgba(16, 185, 129, 0.2)',
                    'borderColor' => 'rgb(16, 185, 129)',
                    'borderWidth' => 2,
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array{labels: array<string>, amounts: array<float>}
     */
    private function getPayoutData(): array
    {
        $labels = [];
        $amounts = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $labels[] = $date->format('M d');

            $dayAmount = SendInstruction::query()
                ->whereIn('state', ['completed', 'processed'])
                ->whereDate('created_at', $date->toDateString())
                ->get()
                ->sum(fn (SendInstruction $instruction): float => (float) $instruction->amount);

            $amounts[] = round($dayAmount, 2);
        }

        return [
            'labels' => $labels,
            'amounts' => $amounts,
        ];
    }
}
