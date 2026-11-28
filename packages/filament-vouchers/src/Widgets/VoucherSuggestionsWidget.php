<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Widgets;

use AIArmada\Cart\Features\Snapshots\Models\CartSnapshot as Cart;
use AIArmada\Cart\Runtime\CartInstanceManager;
use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRecordOwnerContext;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\FilamentVouchers\Support\CartSnapshotAccess;
use AIArmada\FilamentVouchers\Support\MoneyHelper;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Data\VoucherType;
use AIArmada\Vouchers\Exceptions\VoucherException;
use AIArmada\Vouchers\Features\CartIntegration\Api\CartVoucherApplicator;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherCatalog;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherWalletReader;
use AIArmada\Vouchers\Features\Lifecycle\Models\Voucher;
use AIArmada\Vouchers\Services\VoucherDiscountCalculator;
use AIArmada\Vouchers\Features\CartIntegration\EntryPoints\CartWithVouchers;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Throwable;

/**
 * Smart widget that suggests eligible vouchers based on cart contents
 */
#[Lazy]
final class VoucherSuggestionsWidget extends Widget
{
    use VerifiesRecordOwnerContext;

    #[Locked]
    public ?Model $record = null;

    /** @var view-string */
    protected string $view = 'filament-vouchers::widgets.voucher-suggestions';

    protected int | string | array $columnSpan = 'full';

    /**
     * Get eligible voucher suggestions for this cart
     *
     * @return Collection<int, array{voucher: Voucher, potential_savings: int<1, max>, savings_text: string, recommendation: string}>
     */
    public function getEligibleVouchers(): Collection
    {
        if (! $this->record instanceof Cart) {
            return collect();
        }

        if (! CartSnapshotAccess::allows($this->record)) {
            return collect();
        }

        try {
            $cartTotal = $this->record->subtotal;
            $cartCurrency = $this->record->currency;

            // Get active vouchers
            $voucherQuery = $this->suggestionQuery();

            /** @var Builder<Voucher> $voucherQuery */
            $vouchers = $voucherQuery
                ->withCount('usages')
                ->live()
                ->where('currency', $cartCurrency)
                ->orderByDesc('updated_at')
                ->limit(50)
                ->get();

            // Resolve the cart and its applied codes once instead of once
            // per candidate voucher.
            $appliedCodes = $this->appliedVoucherCodes();
            $snapshots = app(VoucherWalletReader::class)->readMany($vouchers->modelKeys());

            // Filter and calculate potential savings
            $suggestions = $vouchers
                ->filter(function (Voucher $voucher) use ($cartTotal, $appliedCodes) {
                    // Check minimum cart value
                    if ($voucher->min_cart_value && $cartTotal < $voucher->min_cart_value) {
                        return false;
                    }

                    // Check if already applied
                    if (in_array($voucher->code, $appliedCodes, true)) {
                        return false;
                    }

                    return true;
                })
                ->map(function (Voucher $voucher) use ($cartTotal, $cartCurrency, $snapshots) {
                    $snapshot = $snapshots[$voucher->id] ?? null;
                    $potentialSavings = $snapshot !== null && $snapshot->redeemable
                        ? $this->calculatePotentialSavings($voucher, $cartTotal, $snapshot->voucher)
                        : 0;
                    $savingsText = MoneyHelper::formatMoney($potentialSavings, $cartCurrency);

                    $recommendation = $this->generateRecommendation($voucher, $cartTotal, $potentialSavings);

                    return [
                        'voucher' => $voucher,
                        'potential_savings' => $potentialSavings,
                        'savings_text' => $savingsText,
                        'recommendation' => $recommendation,
                    ];
                })
                ->filter(fn (array $suggestion) => $suggestion['potential_savings'] > 0)
                ->sortByDesc('potential_savings')
                ->take(5); // Show top 5 suggestions

            return $suggestions;
        } catch (Throwable $exception) {
            Log::error('Failed to load voucher suggestions', [
                'cart_id' => $this->record->id ?? null,
                'error' => $exception->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Apply a suggested voucher.
     *
     * The blade passes the voucher record key (never the code) so quoted
     * payloads cannot break out of the Livewire action argument; the code
     * is resolved server-side inside the current owner scope.
     */
    public function applySuggestion(string $voucherId): void
    {
        if (! $this->record instanceof Cart) {
            return;
        }

        if (! CartSnapshotAccess::allows($this->record)) {
            Notification::make()
                ->warning()
                ->title('Not Authorized')
                ->body('You cannot modify this cart.')
                ->icon(Heroicon::OutlinedExclamationCircle)
                ->send();

            return;
        }

        try {
            $voucher = $this->resolveVoucherForSuggestion($voucherId);

            if ($voucher === null) {
                Notification::make()
                    ->danger()
                    ->title('Cannot Apply Voucher')
                    ->body('The selected voucher is no longer available.')
                    ->send();

                return;
            }

            $cartInstance = app(CartInstanceManager::class)->resolveForSnapshot($this->record->toSnapshotData());

            app(CartVoucherApplicator::class)->handle($cartInstance, $voucher->code, expectedVoucherId: (string) $voucher->getKey());

            Notification::make()
                ->success()
                ->title('Voucher Applied!')
                ->body("Voucher '{$voucher->code}' has been applied.")
                ->icon(Heroicon::OutlinedCheckCircle)
                ->send();

            // Refresh the page
            $this->dispatch('$refresh');

        } catch (VoucherException $exception) {
            Notification::make()
                ->danger()
                ->title('Cannot Apply Voucher')
                ->body($exception->getMessage())
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('Failed to apply voucher. Please try again.')
                ->send();

            Log::error('Failed to apply suggested voucher', [
                'voucher_id' => $voucherId,
                'cart_id' => $this->record->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function appliedVoucherCodes(): array
    {
        try {
            /** @var Cart $cartRecord */
            $cartRecord = $this->record;
            $cartInstance = app(CartInstanceManager::class)->resolveForSnapshot($cartRecord->toSnapshotData());

            $appliedVouchers = array_map(static fn (VoucherCondition $condition) => $condition->getVoucher(), (new CartWithVouchers($cartInstance))->getAppliedVouchers());

            return collect($appliedVouchers)->pluck('code')->filter()->values()->all();
        } catch (Throwable) {
            // If we can't check, assume nothing is applied.
            return [];
        }
    }

    /** @return Builder<Voucher> */
    private function suggestionQuery(): Builder
    {
        $query = Voucher::query();

        if (! config('vouchers.owner.enabled', false)) {
            return $query;
        }

        $scope = Voucher::ownerScopeConfig();
        $owner = OwnerContext::resolveConfigured($scope);
        OwnerContext::assertResolvedOrExplicitGlobal($owner);

        return $query->forOwner($owner, $scope->includeGlobal && ! OwnerScopeOverride::suppressIncludeGlobal());
    }

    private function resolveVoucherForSuggestion(string $voucherId): ?Voucher
    {
        $voucherQuery = $this->suggestionQuery();

        /** @var Voucher|null $voucher */
        $voucher = $voucherQuery->whereKey($voucherId)->first();

        return $voucher;
    }

    /**
     * Calculate potential savings for a voucher.
     *
     * Estimates delegate to the domain discount calculator so checkout math
     * cannot drift from suggestions; unpersisted models fall back to the
     * local simple-type estimate.
     */
    protected function calculatePotentialSavings(Voucher $voucher, int $cartTotal, ?VoucherData $snapshot = null): int
    {
        if ($voucher->exists) {
            $snapshot ??= app(VoucherCatalog::class)->find($voucher->code, $voucher->id);
            $savings = $snapshot === null ? 0 : app(VoucherDiscountCalculator::class)->calculate($snapshot, $cartTotal);
        } else {
            $savings = $this->simpleTypeEstimate($voucher, $cartTotal);
        }

        // Apply max discount cap if set
        if ($voucher->max_discount && $savings > $voucher->max_discount) {
            $savings = $voucher->max_discount;
        }

        return max(0, $savings);
    }

    private function simpleTypeEstimate(Voucher $voucher, int $cartTotal): int
    {
        return match ($voucher->type) {
            VoucherType::Percentage => intdiv(($cartTotal * $voucher->value) + 5000, 10000), // value is in basis points (1000 = 10%)
            VoucherType::Fixed => $voucher->value, // value is in cents
            default => 0,
        };
    }

    /**
     * Generate recommendation text
     */
    protected function generateRecommendation(Voucher $voucher, int $cartTotal, int $potentialSavings): string
    {
        if ($voucher->type === VoucherType::FreeShipping) {
            return 'Get free shipping on this order';
        }

        $savingsPercentage = $cartTotal > 0 ? round(($potentialSavings / $cartTotal) * 100, 1) : 0;

        if ($savingsPercentage >= 20) {
            return "Save {$savingsPercentage}% on your order!";
        }

        if ($voucher->expires_at && $voucher->expires_at->diffInDays() <= 3) {
            return 'Expires in ' . $voucher->expires_at->diffInDays() . ' days!';
        }

        if ($voucher->usage_limit) {
            $remaining = $voucher->getRemainingUses();
            if ($remaining && $remaining <= 10) {
                return "Only {$remaining} uses left!";
            }
        }

        return 'Apply now to save on your order';
    }
}
