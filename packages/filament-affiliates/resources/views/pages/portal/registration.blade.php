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
