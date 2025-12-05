<x-filament-panels::page>
    @if(!$hasAffiliate)
        <x-filament::section>
            <div class="text-center py-8">
                <x-heroicon-o-user-plus class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('No Affiliate Account') }}</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('You do not have an affiliate account yet.') }}</p>
            </div>
        </x-filament::section>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            {{-- Total Earnings --}}
            <x-filament::section>
                <div class="text-center">
                    <x-heroicon-o-currency-dollar class="mx-auto h-8 w-8 text-success-500" />
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {{ $this->formatAmount($totalEarnings) }}
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Total Earnings') }}</p>
                </div>
            </x-filament::section>

            {{-- Pending Earnings --}}
            <x-filament::section>
                <div class="text-center">
                    <x-heroicon-o-clock class="mx-auto h-8 w-8 text-warning-500" />
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {{ $this->formatAmount($pendingEarnings) }}
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Pending Earnings') }}</p>
                </div>
            </x-filament::section>

            {{-- Total Clicks --}}
            <x-filament::section>
                <div class="text-center">
                    <x-heroicon-o-cursor-arrow-rays class="mx-auto h-8 w-8 text-primary-500" />
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {{ number_format($totalClicks) }}
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Total Clicks') }}</p>
                </div>
            </x-filament::section>

            {{-- Total Conversions --}}
            <x-filament::section>
                <div class="text-center">
                    <x-heroicon-o-chart-bar class="mx-auto h-8 w-8 text-info-500" />
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {{ number_format($totalConversions) }}
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Total Conversions') }}</p>
                </div>
            </x-filament::section>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Affiliate Info --}}
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Your Affiliate Account') }}
                </x-slot>

                <div class="space-y-4">
                    <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-700">
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('Affiliate Code') }}</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $affiliate->code }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-700">
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('Name') }}</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $affiliate->name }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-700">
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('Status') }}</span>
                        <x-filament::badge :color="$affiliate->status->value === 'active' ? 'success' : ($affiliate->status->value === 'pending' ? 'warning' : 'gray')">
                            {{ $affiliate->status->label() }}
                        </x-filament::badge>
                    </div>
                    <div class="flex justify-between items-center py-2">
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('Commission Rate') }}</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                            {{ $affiliate->commission_type->value === 'percentage' ? ($affiliate->commission_rate / 100) . '%' : $this->formatAmount($affiliate->commission_rate) }}
                        </span>
                    </div>
                </div>
            </x-filament::section>

            {{-- Recent Conversions --}}
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Recent Conversions') }}
                </x-slot>

                <x-slot name="headerEnd">
                    <x-filament::link
                        :href="route('filament.' . config('filament-affiliates.portal.panel_id', 'affiliate') . '.pages.portal-conversions')"
                        color="primary"
                    >
                        {{ __('View All') }}
                    </x-filament::link>
                </x-slot>

                @if($recentConversions->isEmpty())
                    <div class="text-center py-6">
                        <x-heroicon-o-chart-bar class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ __('No conversions yet') }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Start sharing your affiliate links to earn commissions.') }}</p>
                    </div>
                @else
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($recentConversions as $conversion)
                            <div class="py-3 first:pt-0 last:pb-0 flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $conversion->order_reference ?? __('Conversion') }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $conversion->occurred_at->format('M d, Y H:i') }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $this->formatAmount($conversion->commission_minor) }}
                                    </span>
                                    <x-filament::badge 
                                        :color="$conversion->status->value === 'approved' ? 'success' : ($conversion->status->value === 'pending' ? 'warning' : 'danger')"
                                        size="sm"
                                    >
                                        {{ ucfirst($conversion->status->value) }}
                                    </x-filament::badge>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
