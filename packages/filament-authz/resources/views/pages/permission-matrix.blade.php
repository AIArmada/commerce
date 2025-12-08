<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Role Selection --}}
        @if($selectedRole)
            <div class="p-4 bg-primary-50 dark:bg-primary-900/20 rounded-lg">
                <p class="text-sm font-medium text-primary-600 dark:text-primary-400">
                    Currently editing: <span class="font-bold">{{ $this->getSelectedRoleName() }}</span>
                </p>
            </div>
        @else
            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Select a role using the button above to view and edit its permissions.
                </p>
            </div>
        @endif

        {{-- Permission Matrix --}}
        @if($selectedRole)
            <div class="overflow-x-auto">
                @foreach($this->getMatrixData() as $group => $permissions)
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold mb-3 capitalize">{{ $group }}</h3>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-2">
                            @foreach($permissions as $permissionName => $permissionData)
                                <label 
                                    class="flex items-center p-3 rounded-lg border cursor-pointer transition-colors
                                        {{ $permissionData['has'] 
                                            ? 'bg-success-50 dark:bg-success-900/20 border-success-300 dark:border-success-700' 
                                            : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700' 
                                        }}"
                                    wire:click="togglePermission('{{ $permissionData['id'] }}')"
                                >
                                    <input 
                                        type="checkbox" 
                                        class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500"
                                        {{ $permissionData['has'] ? 'checked' : '' }}
                                        readonly
                                    >
                                    <span class="ml-2 text-sm">
                                        {{ str_replace($group . '.', '', $permissionName) }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
