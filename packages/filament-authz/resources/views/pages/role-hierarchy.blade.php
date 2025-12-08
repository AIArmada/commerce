<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Hierarchy Tree --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold mb-4">Role Hierarchy</h3>
            
            @if(count($hierarchyTree) > 0)
                <div class="space-y-2">
                    @foreach($hierarchyTree as $node)
                        @include('filament-authz::partials.role-tree-node', ['node' => $node])
                    @endforeach
                </div>
            @else
                <p class="text-gray-500 dark:text-gray-400">No roles found. Create a role to get started.</p>
            @endif
        </div>

        {{-- Legend --}}
        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Legend</h4>
            <div class="flex flex-wrap gap-4 text-sm text-gray-600 dark:text-gray-400">
                <span class="flex items-center gap-1">
                    <span class="w-3 h-3 rounded-full bg-primary-500"></span>
                    Root Role
                </span>
                <span class="flex items-center gap-1">
                    <span class="w-3 h-3 rounded-full bg-gray-400"></span>
                    Child Role
                </span>
            </div>
        </div>
    </div>
</x-filament-panels::page>
