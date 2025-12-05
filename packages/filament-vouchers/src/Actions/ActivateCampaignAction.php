<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Actions;

use AIArmada\Vouchers\Campaigns\Enums\CampaignStatus;
use AIArmada\Vouchers\Campaigns\Models\Campaign;
use AIArmada\Vouchers\Campaigns\Services\CampaignService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

final class ActivateCampaignAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'activate_campaign';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Activate');
        $this->icon(Heroicon::OutlinedPlay);
        $this->color('success');
        $this->requiresConfirmation();
        $this->modalHeading('Activate Campaign');
        $this->modalDescription('This will make the campaign active and allow voucher redemptions.');

        $this->visible(fn (Campaign $record): bool => $record->status->canTransitionTo(CampaignStatus::Active));

        $this->action(function (Campaign $record): void {
            /** @var CampaignService $service */
            $service = app(CampaignService::class);
            $service->activate($record);

            Notification::make()
                ->title('Campaign activated')
                ->success()
                ->send();
        });
    }
}
