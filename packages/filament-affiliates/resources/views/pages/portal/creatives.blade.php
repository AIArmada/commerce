<x-filament-panels::page>
    @if (! $hasAffiliate)
        <div class="flex flex-col items-center justify-center py-16 px-4 sm:px-6 lg:px-8 bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800">
            <div class="p-4 bg-primary-50 dark:bg-primary-900/30 rounded-full mb-4">
                <x-heroicon-o-user-plus class="w-12 h-12 text-primary-500" />
            </div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-white text-center mb-2">{{ __('No Affiliate Account') }}</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 text-center max-w-sm">{{ __('You need an active affiliate account to access promotional marketing materials.') }}</p>
        </div>
    @else
        @php
            $groupedGeneral = collect($generalCreatives)->groupBy('type');
            $allTypes = [
                'banner' => ['label' => 'Banners', 'icon' => 'heroicon-o-photo'],
                'image' => ['label' => 'Images', 'icon' => 'heroicon-o-camera'],
                'video' => ['label' => 'Videos', 'icon' => 'heroicon-o-video-camera'],
                'pdf' => ['label' => 'Documents', 'icon' => 'heroicon-o-document-text'],
                'other' => ['label' => 'Other', 'icon' => 'heroicon-o-squares-2x2'],
            ];
        @endphp

        {{-- Enhanced Stats bar --}}
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
            @foreach ($allTypes as $typeKey => $typeData)
                @php $count = collect($generalCreatives)->where('type', $typeKey)->count() + collect($programCreatives)->sum(fn ($p) => collect($p['creatives'])->where('type', $typeKey)->count()); @endphp
                <div class="bg-white dark:bg-gray-900 rounded-2xl p-5 shadow-sm border border-gray-100 dark:border-gray-800 flex items-center gap-4 group hover:shadow-md hover:border-primary-100 dark:hover:border-primary-900/50 transition-all duration-300">
                    <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl group-hover:bg-primary-50 dark:group-hover:bg-primary-900/30 transition-colors">
                        @svg($typeData['icon'], 'w-6 h-6 text-gray-400 dark:text-gray-500 group-hover:text-primary-500 transition-colors')
                    </div>
                    <div>
                        <div class="text-2xl font-black text-gray-900 dark:text-white tracking-tight">{{ $count }}</div>
                        <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __($typeData['label']) }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- General creatives section --}}
        @if (! empty($generalCreatives))
            <div class="mb-10">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight">{{ __('General Marketing Materials') }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ __('Ready-to-use assets for your promotional campaigns. Click to copy links or download.') }}</p>
                </div>

                <div x-data="{ activeTab: 'all' }">
                    {{-- Modern Tabs --}}
                    <div class="flex gap-2 mb-8 overflow-x-auto pb-2 scrollbar-hide">
                        <button @click="activeTab = 'all'"
                                :class="activeTab === 'all' ? 'bg-primary-600 text-white shadow-md ring-1 ring-primary-600' : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-800'"
                                class="px-5 py-2.5 rounded-full text-sm font-semibold transition-all duration-200 whitespace-nowrap">
                            {{ __('All Materials') }}
                        </button>
                        @foreach ($allTypes as $typeKey => $typeData)
                            @php $typeCount = collect($generalCreatives)->where('type', $typeKey)->count(); @endphp
                            @if ($typeCount > 0)
                                <button @click="activeTab = '{{ $typeKey }}'"
                                        :class="activeTab === '{{ $typeKey }}' ? 'bg-primary-600 text-white shadow-md ring-1 ring-primary-600' : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-800'"
                                        class="px-5 py-2.5 rounded-full text-sm font-semibold transition-all duration-200 whitespace-nowrap flex items-center gap-2">
                                    @svg($typeData['icon'], 'w-4 h-4 opacity-70')
                                    {{ __($typeData['label']) }}
                                    <span class="bg-black/10 dark:bg-white/10 px-2 py-0.5 rounded-full text-xs">{{ $typeCount }}</span>
                                </button>
                            @endif
                        @endforeach
                    </div>

                    {{-- Grid --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        @foreach ($generalCreatives as $creative)
                            @include('filament-affiliates::pages.portal.partials.creative-card', ['creative' => $creative])
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- Program creatives --}}
        @foreach ($programCreatives as $program)
            @if (! empty($program['creatives']))
                <div class="mb-10 pt-10 border-t border-gray-200 dark:border-gray-800">
                    <div class="mb-6">
                        <div class="flex items-center gap-3">
                            <h2 class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight">{{ $program['name'] }}</h2>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900/30 dark:text-primary-400">
                                {{ __('Program specific') }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ __('Exclusive assets available through this specific program.') }}</p>
                    </div>

                    <div x-data="{ activeTab: 'all' }">
                        <div class="flex gap-2 mb-8 overflow-x-auto pb-2 scrollbar-hide">
                            <button @click="activeTab = 'all'"
                                    :class="activeTab === 'all' ? 'bg-primary-600 text-white shadow-md ring-1 ring-primary-600' : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-800'"
                                    class="px-5 py-2.5 rounded-full text-sm font-semibold transition-all duration-200 whitespace-nowrap">
                                {{ __('All Materials') }}
                            </button>
                            @foreach ($allTypes as $typeKey => $typeData)
                                @php $typeCount = collect($program['creatives'])->where('type', $typeKey)->count(); @endphp
                                @if ($typeCount > 0)
                                    <button @click="activeTab = '{{ $typeKey }}'"
                                            :class="activeTab === '{{ $typeKey }}' ? 'bg-primary-600 text-white shadow-md ring-1 ring-primary-600' : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-800'"
                                            class="px-5 py-2.5 rounded-full text-sm font-semibold transition-all duration-200 whitespace-nowrap flex items-center gap-2">
                                        @svg($typeData['icon'], 'w-4 h-4 opacity-70')
                                        {{ __($typeData['label']) }}
                                        <span class="bg-black/10 dark:bg-white/10 px-2 py-0.5 rounded-full text-xs">{{ $typeCount }}</span>
                                    </button>
                                @endif
                            @endforeach
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                            @foreach ($program['creatives'] as $creative)
                                @include('filament-affiliates::pages.portal.partials.creative-card', ['creative' => $creative])
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        @endforeach

        {{-- Empty state --}}
        @if (empty($generalCreatives) && empty(array_filter($programCreatives, fn ($p) => ! empty($p['creatives']))))
            <div class="flex flex-col items-center justify-center py-20 px-4 sm:px-6 lg:px-8 bg-white dark:bg-gray-900 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-800">
                <div class="p-6 bg-gray-50 dark:bg-gray-800/50 rounded-full mb-6 ring-8 ring-gray-50/50 dark:ring-gray-800/20">
                    <x-heroicon-o-photo class="w-16 h-16 text-gray-400" />
                </div>
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white text-center mb-3">{{ __('No Marketing Materials Available') }}</h3>
                <p class="text-base text-gray-500 dark:text-gray-400 text-center max-w-md">{{ __('Check back later for banners, videos, documents, and other promotional assets for your campaigns.') }}</p>
            </div>
        @endif
    @endif
</x-filament-panels::page>
