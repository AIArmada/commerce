<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Role Hierarchy
        </x-slot>

        @php $hierarchy = $this->getHierarchy(); @endphp

        @if(count($hierarchy) > 0)
            <div class="space-y-2">
                @foreach($hierarchy as $node)
                    @include('filament-authz::partials.role-tree-node', ['node' => $node])
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">
                No roles configured yet.
            </p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
