<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\SendInstructionResource\Pages;

use AIArmada\Chip\Features\Payouts\Actions\CreateSendInstruction as CreateSendInstructionAction;
use AIArmada\FilamentChip\Resources\SendInstructionResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Override;
use Throwable;

final class CreateSendInstruction extends CreateRecord
{
    protected static string $resource = SendInstructionResource::class;

    #[Override]
    public function getTitle(): string
    {
        return 'Create Payout';
    }

    #[Override]
    public function getSubheading(): string
    {
        return 'Send a payout to a verified bank account via CHIP Send.';
    }

    /** @param array<string, mixed> $data */
    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreateSendInstructionAction::class)->handle(
                amount: (string) $data['amount'],
                bankAccountId: (int) $data['bank_account_id'],
                description: (string) $data['description'],
                reference: (string) $data['reference'],
                email: (string) $data['email'],
            );
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Failed to create payout')
                ->body('The request could not be completed. Check its status before retrying.')
                ->danger()
                ->send();

            throw new Halt;
        }
    }

    #[Override]
    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
