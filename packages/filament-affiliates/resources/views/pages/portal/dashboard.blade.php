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
            <div class="fia-portal-hero">
                <div class="fia-portal-hero-copy">
                    <p class="fia-portal-hero-eyebrow">{{ __('Total Earnings') }}</p>
                    <p class="fia-portal-hero-amount">{{ $this->formatAmount($totalEarnings) }}</p>

                    @if ($availableEarnings > 0)
                        <p class="fia-portal-hero-subtext">
                            {{ $this->formatAmount($availableEarnings) }} {{ __('available for payout') }}
                        </p>
                    @endif
                </div>

                <div class="fia-portal-hero-actions">
                    <a
                        href="{{ route('filament.' . config('filament-affiliates.portal.panel_id', 'affiliate') . '.pages.portal-links') }}"
                        class="fia-portal-hero-link"
                    >
                        <x-heroicon-o-link class="fia-portal-inline-icon" />
                        <span>{{ __('Get Links') }}</span>
                    </a>
                </div>
            </div>

            <div class="fia-portal-stats">
                <x-filament::section class="fia-portal-stat-card">
                    <div class="fia-portal-stat">
                        <div class="fia-portal-stat-icon fia-portal-stat-icon--success">
                            <x-heroicon-o-currency-dollar />
                        </div>

                        <div class="fia-portal-stat-copy">
                            <p class="fia-portal-stat-label">{{ __('Earnings') }}</p>
                            <p class="fia-portal-stat-value">{{ $this->formatAmount($totalEarnings) }}</p>
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section class="fia-portal-stat-card">
                    <div class="fia-portal-stat">
                        <div class="fia-portal-stat-icon fia-portal-stat-icon--primary">
                            <x-heroicon-o-banknotes />
                        </div>

                        <div class="fia-portal-stat-copy">
                            <p class="fia-portal-stat-label">{{ __('Available') }}</p>
                            <p class="fia-portal-stat-value">{{ $this->formatAmount($availableEarnings) }}</p>
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section class="fia-portal-stat-card">
                    <div class="fia-portal-stat">
                        <div class="fia-portal-stat-icon fia-portal-stat-icon--warning">
                            <x-heroicon-o-clock />
                        </div>

                        <div class="fia-portal-stat-copy">
                            <p class="fia-portal-stat-label">{{ __('Pending') }}</p>
                            <p class="fia-portal-stat-value">{{ $this->formatAmount($pendingEarnings) }}</p>
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section class="fia-portal-stat-card">
                    <div class="fia-portal-stat">
                        <div class="fia-portal-stat-icon fia-portal-stat-icon--indigo">
                            <x-heroicon-o-cursor-arrow-rays />
                        </div>

                        <div class="fia-portal-stat-copy">
                            <p class="fia-portal-stat-label">{{ __('Clicks') }}</p>
                            <p class="fia-portal-stat-value">{{ number_format($totalClicks) }}</p>
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section class="fia-portal-stat-card">
                    <div class="fia-portal-stat">
                        <div class="fia-portal-stat-icon fia-portal-stat-icon--info">
                            <x-heroicon-o-arrow-trending-up />
                        </div>

                        <div class="fia-portal-stat-copy">
                            <p class="fia-portal-stat-label">{{ __('Conv. Rate') }}</p>
                            <p class="fia-portal-stat-value">{{ $conversionRate }}%</p>
                        </div>
                    </div>
                </x-filament::section>
            </div>

            <div class="fia-portal-grid">
                <x-filament::section>
                    <x-slot name="heading">
                        {{ __('Your Account') }}
                    </x-slot>

                    <div class="fia-portal-detail-list">
                        <div class="fia-portal-detail-row">
                            <span class="fia-portal-detail-label">{{ __('Code') }}</span>

                            <div class="fia-portal-detail-value">
                                <code class="fia-portal-code">{{ $affiliate->code }}</code>

                                <x-filament::icon-button
                                    icon="heroicon-o-clipboard-document"
                                    size="sm"
                                    x-on:click="navigator.clipboard.writeText('{{ $affiliate->code }}'); $tooltip('Copied!')"
                                />
                            </div>
                        </div>

                        <div class="fia-portal-detail-row">
                            <span class="fia-portal-detail-label">{{ __('Rate') }}</span>

                            <span class="fia-portal-detail-value">
                                {{ $affiliate->commission_type->value === 'percentage' ? ($affiliate->commission_rate / 100) . '%' : $this->formatAmount($affiliate->commission_rate) }}
                            </span>
                        </div>

                        <div class="fia-portal-detail-row">
                            <span class="fia-portal-detail-label">{{ __('Status') }}</span>

                            <span class="fia-portal-detail-value">
                                <x-filament::badge :color="$affiliate->status->color()" size="sm">
                                    {{ $affiliate->status->label() }}
                                </x-filament::badge>
                            </span>
                        </div>

                        <div class="fia-portal-detail-row">
                            <span class="fia-portal-detail-label">{{ __('Conversions') }}</span>

                            <span class="fia-portal-detail-value">{{ number_format($totalConversions) }}</span>
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">
                        {{ __('Recent Conversions') }}
                    </x-slot>

                    <x-slot name="headerEnd">
                        <x-filament::link
                            :href="route('filament.' . config('filament-affiliates.portal.panel_id', 'affiliate') . '.pages.portal-conversions')"
                            color="primary"
                            size="sm"
                        >
                            {{ __('View All') }}
                        </x-filament::link>
                    </x-slot>

                    @if ($recentConversions->isEmpty())
                        <div class="fia-portal-empty">
                            <x-heroicon-o-chart-bar class="fia-portal-empty-icon" />
                            <h3 class="fia-portal-empty-title">{{ __('No conversions yet') }}</h3>
                            <p class="fia-portal-empty-copy">{{ __('Start sharing your affiliate links to earn commissions.') }}</p>
                        </div>
                    @else
                        <div class="fia-portal-table-wrap">
                            <table class="fia-portal-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Order') }}</th>
                                        <th>{{ __('Date') }}</th>
                                        <th class="fia-portal-amount-cell">{{ __('Amount') }}</th>
                                        <th class="fia-portal-status">{{ __('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentConversions as $conversion)
                                        <tr>
                                            <td>
                                                <span class="fia-portal-order">{{ $conversion->order_reference ?? '—' }}</span>
                                            </td>
                                            <td>
                                                <span class="fia-portal-date">{{ $conversion->occurred_at->format('M j, Y') }}</span>
                                            </td>
                                            <td class="fia-portal-amount-cell">
                                                <span class="fia-portal-amount">
                                                    +{{ $this->formatAmount($conversion->commission_minor) }}
                                                </span>
                                            </td>
                                            <td class="fia-portal-status">
                                                <x-filament::badge :color="$conversion->status->color()" size="sm">
                                                    {{ $conversion->status->label() }}
                                                </x-filament::badge>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-filament::section>
            </div>
        </div>
    @endif
</x-filament-panels::page>
