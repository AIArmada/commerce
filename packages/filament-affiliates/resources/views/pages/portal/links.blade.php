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
        <div class="space-y-6">
            {{-- Default Link --}}
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Your Affiliate Link') }}
                </x-slot>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ __('Affiliate Code') }}
                        </label>
                        <div class="flex items-center gap-2">
                            <code class="flex-1 px-3 py-2 bg-gray-100 dark:bg-gray-800 rounded-lg text-sm font-mono">
                                {{ $affiliateCode }}
                            </code>
                            <x-filament::icon-button
                                icon="heroicon-o-clipboard-document"
                                x-on:click="navigator.clipboard.writeText('{{ $affiliateCode }}'); $tooltip('Copied!')"
                            />
                        </div>
                    </div>

                    @if($defaultLink)
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ __('Default Referral Link') }}
                            </label>
                            <div class="flex items-center gap-2">
                                <code class="flex-1 px-3 py-2 bg-gray-100 dark:bg-gray-800 rounded-lg text-sm font-mono truncate">
                                    {{ $defaultLink }}
                                </code>
                                <x-filament::icon-button
                                    icon="heroicon-o-clipboard-document"
                                    x-on:click="navigator.clipboard.writeText('{{ $defaultLink }}'); $tooltip('Copied!')"
                                />
                            </div>
                        </div>
                    @endif
                </div>
            </x-filament::section>

            {{-- Link Generator --}}
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Generate Custom Link') }}
                </x-slot>

                <form wire:submit.prevent="generateLink" class="space-y-4">
                    <div>
                        <label for="targetUrl" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ __('Target URL') }}
                        </label>
                        <x-filament::input.wrapper>
                            <x-filament::input
                                type="url"
                                id="targetUrl"
                                wire:model.defer="targetUrl"
                                placeholder="https://example.com/product"
                            />
                        </x-filament::input.wrapper>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ __('Enter the URL you want to share with your affiliate code.') }}
                        </p>
                    </div>

                    <x-filament::button type="submit">
                        {{ __('Generate Link') }}
                    </x-filament::button>

                    @if($generatedLink)
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ __('Generated Link') }}
                            </label>
                            <div class="flex items-center gap-2">
                                <code class="flex-1 px-3 py-2 bg-success-50 dark:bg-success-900/20 border border-success-200 dark:border-success-800 rounded-lg text-sm font-mono truncate">
                                    {{ $generatedLink }}
                                </code>
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

            {{-- Tips --}}
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Tips for Success') }}
                </x-slot>

                <div class="prose dark:prose-invert text-sm">
                    <ul>
                        <li>{{ __('Share your affiliate links on social media, blogs, and websites.') }}</li>
                        <li>{{ __('Include your links in product reviews and recommendations.') }}</li>
                        <li>{{ __('Track your conversions regularly to optimize your strategy.') }}</li>
                        <li>{{ __('Contact support if you need custom promotional materials.') }}</li>
                    </ul>
                </div>
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
