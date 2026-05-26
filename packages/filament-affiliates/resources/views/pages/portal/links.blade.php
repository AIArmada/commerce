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
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Your Affiliate Link') }}
                </x-slot>

                <div class="fia-portal-field-grid">
                    <div class="fia-portal-field">
                        <label class="fia-portal-label">{{ __('Affiliate Code') }}</label>

                        <div class="fia-portal-inline-code">
                            <code class="fia-portal-code-box">{{ $affiliateCode }}</code>

                            <x-filament::icon-button
                                icon="heroicon-o-clipboard-document"
                                x-on:click="navigator.clipboard.writeText('{{ $affiliateCode }}'); $tooltip('Copied!')"
                            />
                        </div>
                    </div>

                    @if ($defaultLink)
                        <div class="fia-portal-field">
                            <label class="fia-portal-label">{{ __('Default Referral Link') }}</label>

                            <div class="fia-portal-inline-code">
                                <code class="fia-portal-code-box">{{ $defaultLink }}</code>

                                <x-filament::icon-button
                                    icon="heroicon-o-clipboard-document"
                                    x-on:click="navigator.clipboard.writeText('{{ $defaultLink }}'); $tooltip('Copied!')"
                                />
                            </div>
                        </div>
                    @endif
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Generate Custom Link') }}
                </x-slot>

                <form wire:submit.prevent="generateLink" class="fia-portal-field-grid">
                    <div class="fia-portal-field">
                        <label for="targetUrl" class="fia-portal-label">{{ __('Target URL') }}</label>

                        <x-filament::input.wrapper>
                            <x-filament::input
                                type="url"
                                id="targetUrl"
                                wire:model.defer="targetUrl"
                                placeholder="https://example.com/product"
                            />
                        </x-filament::input.wrapper>

                        <p class="fia-portal-helper">
                            {{ __('Enter the URL you want to share with your affiliate code.') }}
                        </p>
                    </div>

                    <div>
                        <x-filament::button type="submit">
                            {{ __('Generate Link') }}
                        </x-filament::button>
                    </div>

                    @if ($generatedLink)
                        <div class="fia-portal-field">
                            <label class="fia-portal-label">{{ __('Generated Link') }}</label>

                            <div class="fia-portal-inline-code">
                                <code class="fia-portal-code-box fia-portal-code-box--success">{{ $generatedLink }}</code>

                                <x-filament::icon-button
                                    icon="heroicon-o-clipboard-document"
                                    color="success"
                                    x-on:click="navigator.clipboard.writeText('{{ $generatedLink }}'); $tooltip('Copied!')"
                                />
                            </div>
                        </div>
                    @endif
                </form>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Tips for Success') }}
                </x-slot>

                <ul class="fia-portal-tips">
                    <li>{{ __('Share your affiliate links on social media, blogs, and websites.') }}</li>
                    <li>{{ __('Include your links in product reviews and recommendations.') }}</li>
                    <li>{{ __('Track your conversions regularly to optimize your strategy.') }}</li>
                    <li>{{ __('Contact support if you need custom promotional materials.') }}</li>
                </ul>
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
