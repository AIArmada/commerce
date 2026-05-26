<?php

declare(strict_types=1);

namespace AIArmada\FilamentPromotions\Actions;

use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\FilamentPromotions\Models\Promotion;
use AIArmada\Promotions\Actions\IssueVouchersFromPromotion;
use AIArmada\Promotions\Support\PromotionsOwnerScope;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Throwable;

final class IssuePromotionVouchersFromListAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Issue Vouchers');
        $this->icon(Heroicon::OutlinedTicket);
        $this->color('primary');
        $this->modalHeading('Issue vouchers from a promotion');
        $this->modalDescription('Select a promotion and generate one-time vouchers without leaving the list page.');
        $this->visible(static fn (): bool => Promotion::supportsIssuedVoucherTracking());
        $this->authorize(static fn (): bool => FilamentPermission::hasAbility('promotion.update') && FilamentPermission::hasAbility('voucher.create'));
        $this->form([
            Select::make('promotion_id')
                ->label('Promotion')
                ->options(static fn (): array => self::promotionOptions())
                ->searchable()
                ->native(false)
                ->required(),

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
                ->helperText('Optional override for generated voucher codes.'),
        ]);
        $this->action(function (array $data): void {
            try {
                $promotion = $this->resolvePromotion((string) $data['promotion_id']);
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

    /**
     * @return array<string, string>
     */
    private static function promotionOptions(): array
    {
        return PromotionsOwnerScope::applyToOwnedQuery(Promotion::query())
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->mapWithKeys(static fn (Promotion $promotion): array => [
                (string) $promotion->getKey() => self::promotionLabel($promotion),
            ])
            ->all();
    }

    private static function promotionLabel(Promotion $promotion): string
    {
        if (is_string($promotion->code) && $promotion->code !== '') {
            return $promotion->name . ' (' . $promotion->code . ')';
        }

        return $promotion->name;
    }

    private function resolvePromotion(string $promotionId): Promotion
    {
        if (! PromotionsOwnerScope::isEnabled()) {
            $promotion = Promotion::query()->find($promotionId);

            if (! $promotion instanceof Promotion) {
                throw (new ModelNotFoundException)->setModel(Promotion::class, [$promotionId]);
            }

            return $promotion;
        }

        /** @var Promotion $promotion */
        $promotion = OwnerWriteGuard::findOrFailForOwner(
            Promotion::class,
            $promotionId,
            PromotionsOwnerScope::resolveOwner(),
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
