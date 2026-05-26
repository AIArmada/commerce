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
            <div class="fia-portal-summary-grid fia-portal-summary-grid--three">
                <x-filament::section class="fia-portal-summary-card">
                    <div class="fia-portal-summary">
                        <x-heroicon-o-chart-bar class="fia-portal-summary-icon fia-portal-summary-icon--primary" />
                        <p class="fia-portal-summary-value">{{ number_format($totalConversions) }}</p>
                        <p class="fia-portal-summary-label">{{ __('Total Conversions') }}</p>
                    </div>
                </x-filament::section>

                <x-filament::section class="fia-portal-summary-card">
                    <div class="fia-portal-summary">
                        <x-heroicon-o-currency-dollar class="fia-portal-summary-icon fia-portal-summary-icon--success" />
                        <p class="fia-portal-summary-value">{{ $this->formatAmount($totalEarnings) }}</p>
                        <p class="fia-portal-summary-label">{{ __('Total Earnings') }}</p>
                    </div>
                </x-filament::section>

                <x-filament::section class="fia-portal-summary-card">
                    <div class="fia-portal-summary">
                        <x-heroicon-o-clock class="fia-portal-summary-icon fia-portal-summary-icon--warning" />
                        <p class="fia-portal-summary-value">{{ $this->formatAmount($pendingEarnings) }}</p>
                        <p class="fia-portal-summary-label">{{ __('Pending Earnings') }}</p>
                    </div>
                </x-filament::section>
            </div>

            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Conversion History') }}
                </x-slot>

                {{ $this->table }}
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
