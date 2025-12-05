<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Actions;

use AIArmada\Vouchers\Campaigns\Models\Campaign;
use AIArmada\Vouchers\Campaigns\Models\CampaignVariant;
use AIArmada\Vouchers\Campaigns\Services\CampaignService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

final class DeclareABWinnerAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'declare_winner';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Declare Winner');
        $this->icon(Heroicon::OutlinedTrophy);
        $this->color('success');
        $this->modalHeading('Declare A/B Test Winner');
        $this->modalDescription('Select the winning variant. Traffic will be shifted 100% to the winner.');

        $this->visible(fn (Campaign $record): bool => $record->ab_testing_enabled && $record->ab_winner_variant === null);

        $this->form(fn (Campaign $record): array => [
            Select::make('variant_id')
                ->label('Winning Variant')
                ->options(fn (): array => $record->variants
                    ->mapWithKeys(fn (CampaignVariant $v): array => [
                        $v->id => sprintf(
                            '%s - %.1f%% CR (%d conversions)',
                            $v->variant_code,
                            $v->applications > 0 ? ($v->conversions / $v->applications) * 100 : 0,
                            $v->conversions
                        ),
                    ])
                    ->toArray())
                ->required(),
        ]);

        $this->action(function (Campaign $record, array $data): void {
            /** @var CampaignService $service */
            $service = app(CampaignService::class);

            $variant = CampaignVariant::find($data['variant_id']);

            if ($variant === null) {
                Notification::make()
                    ->title('Variant not found')
                    ->danger()
                    ->send();

                return;
            }

            $service->declareWinner($record, $variant);

            Notification::make()
                ->title('Winner declared')
                ->body("Variant {$variant->variant_code} is now the winner.")
                ->success()
                ->send();
        });
    }
}
