@php
    $affiliateCodeCheckingCss = <<<'CSS'
[style*="--affiliate-code-checking: 1"] .fi-input-wrp-suffix svg {
    animation: affiliate-spin 1s linear infinite;
}
@keyframes affiliate-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
CSS;

    $debugCookie = null;
    $debugAttribution = null;
    $debugAffiliate = null;
    $debugRawCookie = null;

    if (app()->environment('local')) {
        $cookieName = config('affiliates.cookies.name', 'affiliate_session');
        $cookieValue = request()->cookie($cookieName);

        if (! $cookieValue) {
            $cookieValue = $_COOKIE[$cookieName] ?? null;
            $debugRawCookie = $cookieValue;
        }

        if ($cookieValue) {
            $debugCookie = $cookieValue;
            $affiliateLookup = app(\AIArmada\Affiliates\Contracts\AffiliateLookup::class);
            $debugAffiliate = $affiliateLookup->findActiveAffiliateByCookie($cookieValue);
            $debugAttribution = $affiliateLookup->findActiveAttributionByCookie($cookieValue);
        }
    }
@endphp

<x-filament-panels::page.simple>
    <style>
        {{ $affiliateCodeCheckingCss }}
    </style>
    <x-slot name="heading">
        {{ $this->getHeading() }}
    </x-slot>

    @if($this->getSubheading())
        <x-slot name="subheading">
            {{ $this->getSubheading() }}
        </x-slot>
    @endif

    @if(!$this->isRegistrationEnabled())
        <x-filament::section>
            <div class="text-center py-8">
                <x-heroicon-o-lock-closed class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('Registration Closed') }}</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Affiliate registration is currently not available.') }}</p>
            </div>
        </x-filament::section>
    @else
        <form wire:submit="register">
            {{ $this->form }}

            <div style="margin-top: 1.5rem;">
                <x-filament::actions
                    :actions="$this->getFormActions()"
                    :full-width="$this->hasFullWidthFormActions()"
                />
            </div>
        </form>

        @if ($debugCookie)
            <div style="margin-top: 1.5rem; padding: 1rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.5rem; font-size: 0.75rem; font-family: monospace; color: #475569;">
                <div style="font-weight: 600; margin-bottom: 0.5rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">Affiliate Cookie Debug</div>

                <div style="margin-bottom: 0.25rem;">
                    <span style="color: #64748b;">Cookie:</span>
                    <span style="color: #0f172a; word-break: break-all;">{{ $debugCookie }}</span>
                    @if ($debugRawCookie)
                        <span style="color: #94a3b8;"> (raw)</span>
                    @endif
                </div>

                @if ($debugAttribution)
                    <div style="margin-bottom: 0.25rem;">
                        <span style="color: #64748b;">Attribution:</span>
                        <span style="color: #0f172a;">{{ $debugAttribution->id }}</span>
                    </div>
                    <div style="margin-bottom: 0.25rem;">
                        <span style="color: #64748b;">Code:</span>
                        <span style="color: #0f172a;">{{ $debugAttribution->affiliate_code }}</span>
                    </div>
                    <div style="margin-bottom: 0.25rem;">
                        <span style="color: #64748b;">Source:</span>
                        <span style="color: #0f172a;">{{ $debugAttribution->source ?? '—' }}</span>
                    </div>
                    <div style="margin-bottom: 0.25rem;">
                        <span style="color: #64748b;">Expires:</span>
                        <span style="color: #0f172a;">{{ $debugAttribution->expires_at?->diffForHumans() ?? '—' }}</span>
                    </div>
                @else
                    <div style="color: #94a3b8;">No matching attribution found.</div>
                @endif

                @if ($debugAffiliate)
                    <div style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid #e2e8f0;">
                        <div style="margin-bottom: 0.25rem;">
                            <span style="color: #64748b;">Affiliate:</span>
                            <span style="color: #0f172a;">{{ $debugAffiliate->id }}</span>
                        </div>
                        <div style="margin-bottom: 0.25rem;">
                            <span style="color: #64748b;">Name:</span>
                            <span style="color: #0f172a;">{{ $debugAffiliate->name }}</span>
                        </div>
                        <div style="margin-bottom: 0.25rem;">
                            <span style="color: #64748b;">Code:</span>
                            <span style="color: #0f172a;">{{ $debugAffiliate->code }}</span>
                        </div>
                        <div style="margin-bottom: 0.25rem;">
                            <span style="color: #64748b;">Status:</span>
                            <span style="color: #0f172a;">{{ $debugAffiliate->status }}</span>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    @endif

    @if(filament()->hasLogin())
        <x-slot name="footer">
            <div class="text-center text-sm text-gray-600 dark:text-gray-400">
                {{ __('Already have an account?') }}
                <x-filament::link :href="filament()->getLoginUrl()">
                    {{ __('Sign in') }}
                </x-filament::link>
            </div>
        </x-slot>
    @endif
</x-filament-panels::page.simple>
