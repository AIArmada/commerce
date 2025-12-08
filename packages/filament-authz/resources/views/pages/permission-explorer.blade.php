<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Roles Summary --}}
        <x-filament::section>
            <x-slot name="heading">
                Roles Summary
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($this->getRolesWithPermissionCounts() as $role)
                    <div class="p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold text-sm">{{ $role['name'] }}</h3>
                            <span class="text-xs px-2 py-1 rounded bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300">
                                {{ $role['guard_name'] }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-2">
                            {{ $role['permissions_count'] }} {{ Str::plural('permission', $role['permissions_count']) }}
                        </p>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        {{-- Permissions Grouped --}}
        <x-filament::section>
            <x-slot name="heading">
                Permissions by Domain
            </x-slot>

            <div class="space-y-4">
                @foreach($this->getPermissionsGrouped() as $domain => $permissions)
                    <div class="border-l-4 border-primary-500 pl-4 py-2">
                        <h3 class="font-bold text-base text-gray-900 dark:text-gray-100 mb-2">
                            {{ Str::title($domain) }}
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                            @foreach($permissions as $permission)
                                <div class="text-sm bg-gray-50 dark:bg-gray-800 rounded px-3 py-2">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ $permission['name'] }}
                                    </div>
                                    @if(count($permission['roles']) > 0)
                                        <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                            Roles: {{ implode(', ', $permission['roles']) }}
                                        </div>
                                    @else
                                        <div class="text-xs text-warning-600 dark:text-warning-400 mt-1">
                                            No roles assigned
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
