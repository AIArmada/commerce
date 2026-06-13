<div x-data="{ copiedLink: false, copiedEmbed: false }"
     x-show="activeTab === 'all' || activeTab === '{{ $creative['type'] }}'"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
     class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm hover:shadow-xl hover:-translate-y-1 border border-gray-200 dark:border-gray-800 overflow-hidden transition-all duration-300 flex flex-col group">

    {{-- Media Preview --}}
    <div class="relative bg-gray-100 dark:bg-gray-800 overflow-hidden" style="aspect-ratio: 16/9;">
        @if ($creative['asset_url'])
            @if (in_array($creative['type'], ['banner', 'image']))
                <img src="{{ $creative['asset_url'] }}" alt="{{ $creative['name'] }}" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 ease-out">
            @elseif ($creative['type'] === 'video')
                <div class="absolute inset-0 flex items-center justify-center z-10 pointer-events-none group-hover:scale-110 transition-transform duration-300">
                    <div class="bg-black/50 backdrop-blur-sm rounded-full p-3 shadow-lg">
                        <x-heroicon-s-play class="w-8 h-8 text-white ml-1" />
                    </div>
                </div>
                <video src="{{ $creative['asset_url'] }}" class="w-full h-full object-cover opacity-90 group-hover:opacity-100 transition-opacity" preload="metadata"></video>
            @elseif ($creative['type'] === 'pdf')
                <div class="absolute inset-0 flex items-center justify-center">
                    <div class="p-4 bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm rounded-2xl shadow-sm group-hover:scale-110 transition-transform duration-300">
                        <x-heroicon-o-document-text class="h-12 w-12 text-primary-500" />
                    </div>
                </div>
            @else
                <div class="absolute inset-0 flex items-center justify-center">
                    <div class="p-4 bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm rounded-2xl shadow-sm group-hover:scale-110 transition-transform duration-300">
                        <x-heroicon-o-paper-clip class="h-12 w-12 text-gray-500" />
                    </div>
                </div>
            @endif
        @else
            <div class="absolute inset-0 flex items-center justify-center">
                <x-heroicon-o-photo class="h-12 w-12 text-gray-300 dark:text-gray-600 group-hover:scale-110 transition-transform duration-300" />
            </div>
        @endif

        {{-- Overlays & Badges --}}
        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>

        <div class="absolute top-3 right-3 flex gap-2">
            <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-bold bg-white/90 dark:bg-gray-900/90 text-gray-800 dark:text-gray-200 backdrop-blur-sm shadow-sm uppercase tracking-wider">
                {{ $creative['type'] }}
            </span>
        </div>

        @if ($creative['dimensions'])
            <div class="absolute bottom-3 left-3 opacity-0 group-hover:opacity-100 transition-opacity duration-300 transform translate-y-2 group-hover:translate-y-0">
                <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-semibold bg-black/60 text-white backdrop-blur-sm shadow-sm">
                    {{ $creative['dimensions'] }}
                </span>
            </div>
        @endif
    </div>

    {{-- Info & Actions --}}
    <div class="p-5 flex-1 flex flex-col">
        <h4 class="font-bold text-gray-900 dark:text-white text-lg leading-tight mb-1">{{ $creative['name'] }}</h4>
        @if ($creative['description'])
            <p class="text-sm text-gray-500 dark:text-gray-400 line-clamp-2 mb-4">{{ $creative['description'] }}</p>
        @else
            <div class="mb-4"></div>
        @endif

        <div class="mt-auto space-y-2">
            <div class="flex gap-2">
                {{-- Copy Link --}}
                <button x-on:click="navigator.clipboard.writeText('{{ $creative['tracking_url'] }}'); copiedLink = true; setTimeout(() => copiedLink = false, 2000)"
                        class="flex-1 inline-flex justify-center items-center gap-1.5 px-3 py-2 text-sm font-semibold rounded-xl bg-primary-50 hover:bg-primary-100 text-primary-700 dark:bg-primary-500/10 dark:hover:bg-primary-500/20 dark:text-primary-400 transition-colors shadow-sm ring-1 ring-inset ring-primary-600/10 dark:ring-primary-400/20">
                    <x-heroicon-m-link class="w-4 h-4" x-show="!copiedLink" />
                    <x-heroicon-m-check class="w-4 h-4 text-green-500" x-show="copiedLink" style="display: none;" />
                    <span x-text="copiedLink ? '{{ __('Copied!') }}' : '{{ __('Copy Link') }}'"></span>
                </button>

                {{-- Download --}}
                @if ($creative['asset_url'])
                    <a href="{{ $creative['asset_url'] }}" target="_blank" download
                       class="inline-flex justify-center items-center px-3 py-2 text-sm font-semibold rounded-xl bg-gray-50 hover:bg-gray-100 text-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700 dark:text-gray-300 transition-colors shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-700 tooltip"
                       title="{{ __('Download Asset') }}">
                        <x-heroicon-m-arrow-down-tray class="w-4 h-4" />
                    </a>
                @endif
            </div>

            @if ($creative['embed_code'])
                <button x-on:click="navigator.clipboard.writeText('{{ addslashes(str_replace("\n", "", $creative['embed_code'])) }}'); copiedEmbed = true; setTimeout(() => copiedEmbed = false, 2000)"
                        class="w-full inline-flex justify-center items-center gap-1.5 px-3 py-2 text-sm font-semibold rounded-xl bg-gray-50 hover:bg-gray-100 text-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700 dark:text-gray-300 transition-colors shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-700">
                    <x-heroicon-m-code-bracket class="w-4 h-4" x-show="!copiedEmbed" />
                    <x-heroicon-m-check class="w-4 h-4 text-green-500" x-show="copiedEmbed" style="display: none;" />
                    <span x-text="copiedEmbed ? '{{ __('Embed Copied!') }}' : '{{ __('Copy Embed Code') }}'"></span>
                </button>
            @endif
        </div>
    </div>
</div>
