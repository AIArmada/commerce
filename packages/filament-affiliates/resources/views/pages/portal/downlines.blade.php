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
                        <x-heroicon-o-users class="fia-portal-summary-icon fia-portal-summary-icon--primary" />
                        <p class="fia-portal-summary-value">{{ number_format($directDownlines) }}</p>
                        <p class="fia-portal-summary-label">{{ __('Direct Downlines') }}</p>
                    </div>
                </x-filament::section>

                <x-filament::section class="fia-portal-summary-card">
                    <div class="fia-portal-summary">
                        <x-heroicon-o-user-group class="fia-portal-summary-icon fia-portal-summary-icon--primary" />
                        <p class="fia-portal-summary-value">{{ number_format($totalDownlines) }}</p>
                        <p class="fia-portal-summary-label">{{ __('Total Downlines') }}</p>
                    </div>
                </x-filament::section>

                <x-filament::section class="fia-portal-summary-card">
                    <div class="fia-portal-summary">
                        <x-heroicon-o-arrow-trending-up class="fia-portal-summary-icon fia-portal-summary-icon--success" />
                        <p class="fia-portal-summary-value">{{ number_format($directDownlines) }}</p>
                        <p class="fia-portal-summary-label">{{ __('Active Sponsorships') }}</p>
                    </div>
                </x-filament::section>
            </div>

            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Downline Affiliates') }}
                </x-slot>

                {{ $this->table }}
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
