<x-filament-panels::page>
    @if (! $hasAffiliate)
        <x-filament::section>
            <div class="fia-portal-empty">
                <x-heroicon-o-user-plus class="fia-portal-empty-icon" />
                <h3 class="fia-portal-empty-title">{{ __('No Affiliate Account') }}</h3>
                <p class="fia-portal-empty-copy">{{ __('You do not have an affiliate account yet.') }}</p>
            </div>
        </x-filament::section>
    @elseif ($vouchers->isEmpty())
        <x-filament::section>
            <x-slot name="heading">
                {{ __('Your Vouchers') }}
            </x-slot>

            <x-slot name="description">
                {{ __('Coupon codes linked to your affiliate account. Share these with your audience to earn conversion credit.') }}
            </x-slot>

            <div class="fia-portal-empty">
                <x-heroicon-o-ticket class="fia-portal-empty-icon" />
                <h3 class="fia-portal-empty-title">{{ __('No vouchers yet') }}</h3>
                <p class="fia-portal-empty-copy">{{ __('Your referral voucher codes will appear here once created.') }}</p>
            </div>
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">
                {{ __('Your Vouchers') }}
            </x-slot>

            <x-slot name="description">
                {{ __('Coupon codes linked to your affiliate account. Share these with your audience to earn conversion credit.') }}
            </x-slot>

            <div class="fia-portal-table-wrap">
                <table class="fia-portal-table">
                    <thead>
                        <tr>
                            <th>{{ __('Code') }}</th>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Value') }}</th>
                            <th>{{ __('Commission') }}</th>
                            <th class="fia-portal-status">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($vouchers as $voucher)
                            <tr>
                                <td>
                                    <div class="fia-portal-detail-value">
                                        <code class="fia-portal-code">{{ $voucher->code }}</code>

                                        <x-filament::icon-button
                                            icon="heroicon-o-clipboard-document"
                                            size="sm"
                                            x-on:click="navigator.clipboard.writeText('{{ $voucher->code }}'); $tooltip('Copied!')"
                                        />
                                    </div>
                                </td>
                                <td>
                                    <span>{{ $voucher->name }}</span>
                                </td>
                                <td>
                                    <x-filament::badge :color="\AIArmada\Vouchers\Enums\VoucherType::from($voucher->type->value)->color()" size="sm">
                                        {{ $voucher->type->label() }}
                                    </x-filament::badge>
                                </td>
                                <td>
                                    <span class="fia-portal-amount">{{ $voucher->value_label }}</span>
                                </td>
                                <td>
                                    @php
                                        $commissionType = $voucher->affiliate_commission_type;
                                        $commissionValue = $voucher->affiliate_commission_value;
                                        
                                        $commissionLabel = __('—');
                                        if ($commissionType && $commissionValue) {
                                            $commissionTypeValue = $commissionType instanceof \AIArmada\Affiliates\Enums\CommissionType 
                                                ? $commissionType->value 
                                                : (string) $commissionType;
                                            
                                            if ($commissionTypeValue === 'percentage') {
                                                $commissionLabel = ($commissionValue / 100) . '%';
                                            } else {
                                                $commissionLabel = $this->formatAmount($commissionValue, $voucher->currency ?? null);
                                            }
                                        }
                                    @endphp
                                    <span class="fia-portal-amount">{{ $commissionLabel }}</span>
                                </td>
                                <td class="fia-portal-status">
                                    @php
                                        $statusColor = match (true) {
                                            $voucher->status instanceof \AIArmada\Vouchers\States\Active => 'success',
                                            $voucher->status instanceof \AIArmada\Vouchers\States\Paused => 'warning',
                                            $voucher->status instanceof \AIArmada\Vouchers\States\Expired => 'danger',
                                            $voucher->status instanceof \AIArmada\Vouchers\States\Depleted => 'gray',
                                            default => 'gray',
                                        };
                                    @endphp
                                    <x-filament::badge :color="$statusColor" size="sm">
                                        {{ $voucher->status->label() }}
                                    </x-filament::badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>