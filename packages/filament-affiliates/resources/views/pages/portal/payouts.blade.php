<x-filament-panels::page>
    @if (! $hasAffiliate)
        <x-filament::section>
            <div class="fia-portal-empty">
                <x-heroicon-o-user-plus class="fia-portal-empty-icon" />
                <h3 class="fia-portal-empty-title">{{ __('No Affiliate Account') }}</h3>
                <p class="fia-portal-empty-copy">{{ __('You do not have an affiliate account yet.') }}</p>
            </div>
        </x-filament::section>
    @else
        <div class="fia-portal-stack">
            <div class="fia-portal-summary-grid fia-portal-summary-grid--two">
                <x-filament::section class="fia-portal-summary-card">
                    <div class="fia-portal-summary">
                        <x-heroicon-o-banknotes class="fia-portal-summary-icon fia-portal-summary-icon--success" />
                        <p class="fia-portal-summary-value">{{ $this->formatAmount($totalPaid) }}</p>
                        <p class="fia-portal-summary-label">{{ __('Total Paid Out') }}</p>
                    </div>
                </x-filament::section>

                <x-filament::section class="fia-portal-summary-card">
                    <div class="fia-portal-summary">
                        <x-heroicon-o-clock class="fia-portal-summary-icon fia-portal-summary-icon--warning" />
                        <p class="fia-portal-summary-value">{{ $this->formatAmount($availableEarnings) }}</p>
                        <p class="fia-portal-summary-label">{{ __('Available for Payout') }}</p>
                    </div>
                </x-filament::section>
            </div>

            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Payout History') }}
                </x-slot>

                {{ $this->table }}
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
