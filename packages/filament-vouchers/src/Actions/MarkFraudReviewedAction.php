<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Actions;

use AIArmada\Vouchers\Fraud\Models\VoucherFraudSignal;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

final class MarkFraudReviewedAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'mark_reviewed';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Mark Reviewed');
        $this->icon(Heroicon::OutlinedCheck);
        $this->color('success');
        $this->modalHeading('Mark Signal as Reviewed');

        $this->visible(fn (VoucherFraudSignal $record): bool => !$record->reviewed);

        $this->form([
            Textarea::make('review_notes')
                ->label('Review Notes')
                ->rows(3)
                ->helperText('Optional notes about your review decision'),
        ]);

        $this->action(function (VoucherFraudSignal $record, array $data): void {
            $reviewerId = Auth::id();

            $record->markReviewed(
                reviewerId: $reviewerId ? (string) $reviewerId : null,
                notes: $data['review_notes'] ?? null
            );

            Notification::make()
                ->title('Signal marked as reviewed')
                ->success()
                ->send();
        });
    }
}
