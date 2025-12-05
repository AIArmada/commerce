<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Actions;

use AIArmada\Vouchers\Campaigns\Enums\CampaignStatus;
use AIArmada\Vouchers\Campaigns\Models\Campaign;
use AIArmada\Vouchers\Campaigns\Services\CampaignService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

final class PauseCampaignAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Pause');
        $this->icon(Heroicon::OutlinedPause);
        $this->color('warning');
        $this->requiresConfirmation();
        $this->modalHeading('Pause Campaign');
        $this->modalDescription('This will temporarily pause the campaign. Vouchers will not be redeemable.');

        $this->visible(fn (Campaign $record): bool => $record->status->canTransitionTo(CampaignStatus::Paused));

        $this->action(function (Campaign $record): void {
            /** @var CampaignService $service */
            $service = app(CampaignService::class);
            $service->pause($record);

            Notification::make()
                ->title('Campaign paused')
                ->warning()
                ->send();
        });
    }

    public static function getDefaultName(): ?string
    {
        return 'pause_campaign';
    }
}
