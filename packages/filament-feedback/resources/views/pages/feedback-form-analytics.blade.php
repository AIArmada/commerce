<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                Response Summary
            </x-slot>

            @php
                $analytics = $this->getAnalytics();
            @endphp

            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <x-filament::section heading="Total Responses">
                    {{ $analytics->totalResponses }}
                </x-filament::section>

                <x-filament::section heading="Completed">
                    {{ $analytics->completedResponses }}
                </x-filament::section>

                <x-filament::section heading="Average Score">
                    {{ $analytics->averageScore ?? '—' }}
                </x-filament::section>

                <x-filament::section heading="Completion Rate">
                    {{ number_format($analytics->completionRate, 2) }}%
                </x-filament::section>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                Latest Comments
            </x-slot>

            @php
                $comments = $this->getLatestComments();
            @endphp

            @if ($comments->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No comments yet.</p>
            @else
                <ul class="space-y-3">
                    @foreach ($comments as $comment)
                        <li class="rounded-lg border border-gray-200 p-3 text-sm dark:border-gray-700">
                            {{ $comment->text_value }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
