<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Latest Comments
        </x-slot>

        @php
            $comments = $this->getComments();
        @endphp

        @if ($comments === [])
            <p class="text-sm text-gray-500 dark:text-gray-400">No comments yet.</p>
        @else
            <ul class="space-y-3">
                @foreach ($comments as $comment)
                    <li class="rounded-lg border border-gray-200 p-3 text-sm dark:border-gray-700">
                        {{ is_array($comment) ? ($comment['text_value'] ?? '') : '' }}
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
