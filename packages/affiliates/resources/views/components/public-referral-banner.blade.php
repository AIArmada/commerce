@props([
    'affiliateReferral' => null,
    'showCheckoutLink' => false,
])

@php
    $affiliateReferral = is_array($affiliateReferral ?? null) ? $affiliateReferral : null;
    $showCheckoutLink = (bool) ($showCheckoutLink ?? false);
    $affiliateCode = $affiliateReferral['code'] ?? null;
    $affiliateName = $affiliateReferral['name'] ?? null;
    $defaultVoucherCode = $affiliateReferral['default_voucher_code'] ?? null;
@endphp

@if ($affiliateReferral !== null && filled($affiliateCode))
    <section data-affiliate-referral-banner class="overflow-hidden rounded-2xl border border-emerald-200 bg-linear-to-r from-emerald-50 to-teal-50 px-4 py-4 text-[#083344] shadow-[0_10px_30px_rgba(13,148,136,0.10)]">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 6 9 17 4 12" />
                    </svg>
                </span>

                <div>
                    <p class="text-[0.68rem] font-black uppercase tracking-[0.18em] text-emerald-700">Referral Applied</p>
                    <p class="mt-1 text-sm font-semibold leading-6 text-[#083344]">
                        You’re here with
                        <span class="text-emerald-800">{{ $affiliateName ?: $affiliateCode }}</span>

                        @if (filled($affiliateName) && filled($affiliateCode))
                            <span class="font-medium text-[#0f766e]">({{ $affiliateCode }})</span>
                        @endif
                    </p>

                    @if (filled($defaultVoucherCode))
                        <p class="mt-1 text-xs leading-5 text-[#365061]">
                            If you need to re-enter the referral discount later, use voucher
                            <span class="font-black text-[#0f766e]">{{ $defaultVoucherCode }}</span>.
                        </p>
                    @else
                        <p class="mt-1 text-xs leading-5 text-[#365061]">
                            Your referral is already being carried through this session, so your checkout path stays attributed correctly.
                        </p>
                    @endif
                </div>
            </div>

            @if ($showCheckoutLink && filled($affiliateReferral['checkout_url'] ?? null))
                <a href="{{ $affiliateReferral['checkout_url'] }}" class="inline-flex items-center justify-center rounded-full bg-emerald-700 px-4 py-2 text-xs font-black uppercase tracking-[0.14em] text-white shadow-[0_8px_20px_rgba(6,95,70,0.20)] transition hover:bg-emerald-800">
                    Go to checkout
                </a>
            @endif
        </div>
    </section>
@endif