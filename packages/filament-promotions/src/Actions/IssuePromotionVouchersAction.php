<?php

declare(strict_types=1);

namespace AIArmada\FilamentPromotions\Actions;

use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Promotions\Actions\IssueVouchersFromPromotion;
use AIArmada\Promotions\Models\Promotion;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

final class IssuePromotionVouchersAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Issue Vouchers');
        $this->icon(Heroicon::OutlinedTicket);
        $this->color('primary');
        $this->modalHeading('Issue vouchers from promotion');
        $this->modalDescription('Create one-time-use vouchers from this promotion for targeted distribution or recovery campaigns.');
        $this->visible(static fn (): bool => Promotion::supportsIssuedVoucherTracking());
        $this->authorize(static fn (): bool => FilamentPermission::hasAbility('promotion.update') && FilamentPermission::hasAbility('voucher.create'));
        $this->form([
            TextInput::make('count')
                ->label('Voucher Count')
                ->numeric()
                ->minValue(1)
                ->maxValue(100)
                ->required()
                ->default(1),

            TextInput::make('code_prefix')
                ->label('Code Prefix')
                ->maxLength(40)
                ->default(static function (Promotion $record): string {
                    $seed = $record->code ?? $record->name;

                    return Str::upper(Str::slug($seed, '-'));
                })
                ->helperText('Optional prefix for generated voucher codes.'),
        ]);
        $this->action(function (Promotion $record, array $data): void {
            try {
                $promotion = $this->resolvePromotion($record);
                $count = max(1, (int) ($data['count'] ?? 1));
                $codePrefix = $this->normalizeCodePrefix($data['code_prefix'] ?? null);

                $issue = static fn (): Collection => IssueVouchersFromPromotion::run($promotion, $count, $codePrefix);
                $issued = $promotion->owner_type === null && $promotion->owner_id === null
                    ? OwnerContext::withOwner(null, $issue)
                    : $issue();

                Notification::make()
                    ->title('Vouchers issued')
                    ->body('Issued ' . $issued->count() . ' vouchers from promotion ' . $promotion->name . '.')
                    ->success()
                    ->send();
            } catch (Throwable $throwable) {
                report($throwable);

                Notification::make()
                    ->title('Unable to issue vouchers')
                    ->body($throwable->getMessage())
                    ->danger()
                    ->send();
            }
        });
    }

    public static function getDefaultName(): ?string
    {
        return 'issue_vouchers';
    }

    private function resolvePromotion(Promotion $record): Promotion
    {
        if (! config('promotions.owner.enabled', true)) {
            return $record;
        }

        /** @var Promotion $promotion */
        $promotion = OwnerWriteGuard::findOrFailForOwner(
            Promotion::class,
            (string) $record->getKey(),
            OwnerContext::resolve(),
            includeGlobal: false,
            message: 'Promotion is not accessible in the current owner scope.',
        );

        return $promotion;
    }

    private function normalizeCodePrefix(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = mb_trim($value);

        return $normalized !== '' ? $normalized : null;
    }
}
