<x-filament-panels::page>
    @if (! $hasAffiliate)
        <x-filament::section>
            <div class="fia-portal-empty">
                <x-heroicon-o-user-plus class="fia-portal-empty-icon" />
                <h3 class="fia-portal-empty-title">{{ __('No Affiliate Account') }}</h3>
                <p class="fia-portal-empty-copy">{{ __('You need an affiliate account to access marketing materials.') }}</p>
            </div>
        </x-filament::section>
    @else
        <div class="fia-portal-stack">
            {{-- General creatives --}}
            @if (! empty($generalCreatives))
                <x-filament::section>
                    <x-slot name="heading">{{ __('General Marketing Materials') }}</x-slot>
                    <x-slot name="description">{{ __('Use these assets anywhere to promote our products.') }}</x-slot>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
                        @foreach ($generalCreatives as $creative)
                            <x-filament::section class="relative">
                                <div class="space-y-3">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <h4 class="font-medium text-sm">{{ $creative['name'] }}</h4>
                                            @if ($creative['description'])
                                                <p class="text-xs text-gray-500 mt-1">{{ $creative['description'] }}</p>
                                            @endif
                                        </div>
                                        <x-filament::badge size="sm">{{ $creative['type'] }}</x-filament::badge>
                                    </div>

                                    @if ($creative['asset_url'])
                                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg overflow-hidden">
                                            @if (in_array($creative['type'], ['banner', 'image']))
                                                <img
                                                    src="{{ $creative['asset_url'] }}"
                                                    alt="{{ $creative['name'] }}"
                                                    class="w-full h-auto max-h-48 object-contain"
                                                    @if ($creative['width']) width="{{ $creative['width'] }}" @endif
                                                    @if ($creative['height']) height="{{ $creative['height'] }}" @endif
                                                >
                                            @elseif ($creative['type'] === 'video')
                                                <video
                                                    src="{{ $creative['asset_url'] }}"
                                                    controls
                                                    class="w-full h-auto max-h-48"
                                                ></video>
                                            @else
                                                <div class="p-4 text-center">
                                                    <x-heroicon-o-document class="h-8 w-8 mx-auto text-gray-400" />
                                                    <p class="text-xs text-gray-500 mt-1">{{ __('Click to preview') }}</p>
                                                </div>
                                            @endif
                                        </div>
                                    @endif

                                    @if ($creative['dimensions'])
                                        <p class="text-xs text-gray-500">{{ $creative['dimensions'] }}</p>
                                    @endif

                                    <div class="space-y-2">
                                        <div>
                                            <label class="text-xs text-gray-500 block">{{ __('Tracking URL') }}</label>
                                            <div class="flex gap-1">
                                                <code class="flex-1 text-xs truncate bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">
                                                    {{ $creative['tracking_url'] }}
                                                </code>
                                                <x-filament::icon-button
                                                    icon="heroicon-o-clipboard-document"
                                                    size="sm"
                                                    x-on:click="navigator.clipboard.writeText('{{ $creative['tracking_url'] }}'); $tooltip('{{ __('Copied!') }}')"
                                                />
                                            </div>
                                        </div>

                                        @if ($creative['embed_code'])
                                            <div>
                                                <label class="text-xs text-gray-500 block">{{ __('Embed Code') }}</label>
                                                <div class="flex gap-1">
                                                    <code class="flex-1 text-xs truncate bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">
                                                        {{ $creative['embed_code'] }}
                                                    </code>
                                                    <x-filament::icon-button
                                                        icon="heroicon-o-clipboard-document"
                                                        size="sm"
                                                        x-on:click="navigator.clipboard.writeText('{{ $creative['embed_code'] }}'); $tooltip('{{ __('Copied!') }}')"
                                                    />
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="flex gap-2 pt-2 border-t border-gray-100 dark:border-gray-700">
                                        <x-filament::link
                                            :href="$creative['tracking_url']"
                                            target="_blank"
                                            size="sm"
                                            icon="heroicon-o-arrow-top-right-on-square"
                                        >
                                            {{ __('Preview') }}
                                        </x-filament::link>
                                    </div>
                                </div>
                            </x-filament::section>
                        @endforeach
                    </div>
                </x-filament::section>
            @endif

            {{-- Program creatives --}}
            @foreach ($programCreatives as $program)
                @if (! empty($program['creatives']))
                    <x-filament::section>
                        <x-slot name="heading">{{ $program['name'] }}</x-slot>
                        <x-slot name="description">{{ __('Assets available through this program.') }}</x-slot>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
                            @foreach ($program['creatives'] as $creative)
                                <x-filament::section class="relative">
                                    <div class="space-y-3">
                                        <div class="flex items-start justify-between">
                                            <div>
                                                <h4 class="font-medium text-sm">{{ $creative['name'] }}</h4>
                                                @if ($creative['description'])
                                                    <p class="text-xs text-gray-500 mt-1">{{ $creative['description'] }}</p>
                                                @endif
                                            </div>
                                            <x-filament::badge size="sm">{{ $creative['type'] }}</x-filament::badge>
                                        </div>

                                        @if ($creative['asset_url'])
                                            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg overflow-hidden">
                                                @if (in_array($creative['type'], ['banner', 'image']))
                                                    <img
                                                        src="{{ $creative['asset_url'] }}"
                                                        alt="{{ $creative['name'] }}"
                                                        class="w-full h-auto max-h-48 object-contain"
                                                        @if ($creative['width']) width="{{ $creative['width'] }}" @endif
                                                        @if ($creative['height']) height="{{ $creative['height'] }}" @endif
                                                    >
                                                @elseif ($creative['type'] === 'video')
                                                    <video
                                                        src="{{ $creative['asset_url'] }}"
                                                        controls
                                                        class="w-full h-auto max-h-48"
                                                    ></video>
                                                @else
                                                    <div class="p-4 text-center">
                                                        <x-heroicon-o-document class="h-8 w-8 mx-auto text-gray-400" />
                                                        <p class="text-xs text-gray-500 mt-1">{{ __('Click to preview') }}</p>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif

                                        @if ($creative['dimensions'])
                                            <p class="text-xs text-gray-500">{{ $creative['dimensions'] }}</p>
                                        @endif

                                        <div class="space-y-2">
                                            <div>
                                                <label class="text-xs text-gray-500 block">{{ __('Tracking URL') }}</label>
                                                <div class="flex gap-1">
                                                    <code class="flex-1 text-xs truncate bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">
                                                        {{ $creative['tracking_url'] }}
                                                    </code>
                                                    <x-filament::icon-button
                                                        icon="heroicon-o-clipboard-document"
                                                        size="sm"
                                                        x-on:click="navigator.clipboard.writeText('{{ $creative['tracking_url'] }}'); $tooltip('{{ __('Copied!') }}')"
                                                    />
                                                </div>
                                            </div>

                                            @if ($creative['embed_code'])
                                                <div>
                                                    <label class="text-xs text-gray-500 block">{{ __('Embed Code') }}</label>
                                                    <div class="flex gap-1">
                                                        <code class="flex-1 text-xs truncate bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">
                                                            {{ $creative['embed_code'] }}
                                                        </code>
                                                        <x-filament::icon-button
                                                            icon="heroicon-o-clipboard-document"
                                                            size="sm"
                                                            x-on:click="navigator.clipboard.writeText('{{ $creative['embed_code'] }}'); $tooltip('{{ __('Copied!') }}')"
                                                        />
                                                    </div>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="flex gap-2 pt-2 border-t border-gray-100 dark:border-gray-700">
                                            <x-filament::link
                                                :href="$creative['tracking_url']"
                                                target="_blank"
                                                size="sm"
                                                icon="heroicon-o-arrow-top-right-on-square"
                                            >
                                                {{ __('Preview') }}
                                            </x-filament::link>
                                        </div>
                                    </div>
                                </x-filament::section>
                            @endforeach
                        </div>
                    </x-filament::section>
                @endif
            @endforeach

            @if (empty($generalCreatives) && empty(array_filter($programCreatives, fn ($p) => ! empty($p['creatives']))))
                <x-filament::section>
                    <div class="fia-portal-empty">
                        <x-heroicon-o-photo class="fia-portal-empty-icon" />
                        <h3 class="fia-portal-empty-title">{{ __('No Marketing Materials Available') }}</h3>
                        <p class="fia-portal-empty-copy">{{ __('Check back later for banners, videos, and other promotional assets.') }}</p>
                    </div>
                </x-filament::section>
            @endif
        </div>
    @endif
</x-filament-panels::page>