<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Resources\SubscriptionResource\Pages;

use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\FilamentCashierChip\Resources\SubscriptionResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Collection;
use Override;
use Throwable;

final class ListSubscriptions extends ListRecords
{
    protected static string $resource = SubscriptionResource::class;

    #[Override]
    public function getTitle(): string
    {
        return 'Subscriptions';
    }

    #[Override]
    public function getSubheading(): string
    {
        return 'Manage subscription billing and lifecycle across all customers.';
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('bulk_pause')
                ->label('Bulk Pause')
                ->icon('heroicon-o-pause')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Pause All Active Subscriptions')
                ->modalDescription('Are you sure you want to pause all active subscriptions? This action will prevent billing for all subscribers.')
                ->action(function (): void {
                    $result = self::transitionSubscriptionsInChunks(
                        SubscriptionStatus::Active,
                        fn (Subscription $subscription): mixed => $subscription->pause(),
                    );

                    Notification::make()
                        ->title('Subscriptions Paused')
                        ->body("Paused: {$result['succeeded']}, Failed: {$result['failed']}.")
                        ->success()
                        ->send();
                }),

            Action::make('bulk_resume')
                ->label('Bulk Resume')
                ->icon('heroicon-o-play')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Resume All Paused Subscriptions')
                ->modalDescription('Are you sure you want to resume all paused subscriptions? This will re-enable billing for all paused subscribers.')
                ->action(function (): void {
                    $result = self::transitionSubscriptionsInChunks(
                        SubscriptionStatus::Paused,
                        fn (Subscription $subscription): mixed => $subscription->unpause(),
                    );

                    Notification::make()
                        ->title('Subscriptions Resumed')
                        ->body("Resumed: {$result['succeeded']}, Failed: {$result['failed']}.")
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * Transition every subscription in the given status through the domain
     * transition, chunked and scoped exactly like the resource table.
     *
     * @param  callable(Subscription): mixed  $transition
     * @return array{succeeded: int, failed: int}
     */
    private static function transitionSubscriptionsInChunks(SubscriptionStatus $from, callable $transition): array
    {
        $succeeded = 0;
        $failed = 0;

        SubscriptionResource::getEloquentQuery()
            ->where('chip_status', $from->value)
            ->chunkById(100, function (Collection $subscriptions) use ($transition, &$succeeded, &$failed): void {
                foreach ($subscriptions as $subscription) {
                    try {
                        $transition($subscription);
                        $succeeded++;
                    } catch (Throwable $exception) {
                        report($exception);
                        $failed++;
                    }
                }
            });

        return ['succeeded' => $succeeded, 'failed' => $failed];
    }
}
