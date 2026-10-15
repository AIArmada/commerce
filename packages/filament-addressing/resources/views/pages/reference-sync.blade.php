<x-filament-panels::page>
    @php($release = $this->releaseInfo())
    <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-xl border p-4"><strong>Baseline revision</strong><p>{{ $release['baseline'] }}</p></div>
        <div class="rounded-xl border p-4"><strong>Available canonical revision</strong><p>{{ $release['incoming'] }}</p></div>
    </div>
    <p class="text-sm text-gray-500">Latest job: {{ $release['job']['status'] ?? 'Not run' }} · {{ $release['job']['operation'] ?? '—' }} @if(($release['job']['status'] ?? null) === 'failed') — {{ $release['job']['error'] ?? 'Unknown failure' }} @endif</p>
    <div class="flex flex-wrap gap-3">
        <x-filament::button wire:click="refreshComparison">Queue comparison</x-filament::button>
        <x-filament::button color="gray" wire:click="loadBundledRelease" wire:confirm="Load the canonical dataset shipped by the installed package?">Load packaged release</x-filament::button>
        <x-filament::button color="warning" wire:click="initializeBaseline" wire:confirm="Only for a pristine, never-modified installation. The system rejects non-identical data. Continue?">Initialize pristine baseline</x-filament::button>
        <x-filament::button color="success" wire:click="exportApproved">Export approved contributions</x-filament::button>
        <x-filament::button color="warning" wire:click="applyApproved" wire:confirm="Apply up to 100 approved, conflict-free canonical updates? Back up the database first.">Apply approved safe updates</x-filament::button>
    </div>
    <p class="text-sm text-gray-500">Canonical bundles must come from a trusted Composer package release. A production app's ZIP is always a proposal, never an automatic source of truth. User addresses are never exported.</p>
    <div class="flex gap-3">
        <label>Queue <select wire:model.live="direction" class="rounded-lg border"><option value="contribution">Contributions (App → Package)</option><option value="delivery">Updates (Package → App)</option></select></label>
        <label>Status <select wire:model.live="filter" class="rounded-lg border"><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option><option value="all">All</option></select></label>
    </div>
    @php($data = $this->rows())
    <p class="text-sm text-gray-500">{{ number_format($data['total']) }} change(s), page {{ $data['page'] }} of {{ $data['maxPage'] }}</p>
    <div class="overflow-x-auto rounded-xl border">
        <table class="w-full text-sm">
            <thead><tr class="border-b text-left"><th class="p-3">Record type / natural key</th><th class="p-3">Operation</th><th class="p-3">Review</th><th class="p-3">Diff</th><th class="p-3">Actions</th></tr></thead>
            <tbody>
                @forelse($data['rows'] as $row)
                    <tr class="border-b align-top">
                        <td class="p-3"><strong>{{ $row->entity_type }}</strong><pre class="whitespace-pre-wrap break-all">{{ $row->natural_key }}</pre></td>
                        <td class="p-3">{{ $row->operation }}</td>
                        <td class="p-3">{{ $row->decision }}</td>
                        <td class="p-3"><details><summary class="cursor-pointer">Before / local / upstream</summary><div class="grid gap-2"><pre class="max-w-lg whitespace-pre-wrap break-all">BASE: {{ $row->baseline_payload }}</pre><pre class="max-w-lg whitespace-pre-wrap break-all">LOCAL: {{ $row->local_payload }}</pre><pre class="max-w-lg whitespace-pre-wrap break-all">UPSTREAM: {{ $row->remote_payload }}</pre></div></details></td>
                        <td class="p-3"><div class="flex flex-wrap gap-2">
                            @if($row->operation !== 'conflict' && $row->decision !== 'applied')
                                <x-filament::button size="xs" wire:click="review({{ $row->id }}, 'approved')">Approve</x-filament::button>
                            @endif
                            <x-filament::button size="xs" color="gray" wire:click="review({{ $row->id }}, 'rejected')">Reject</x-filament::button>
                        </div></td>
                    </tr>
                @empty
                    <tr><td class="p-4" colspan="5">No changes in this queue.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <h3 class="font-semibold">Recent contribution exports</h3>
    <div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr><th class="p-2 text-left">Batch</th><th class="p-2 text-left">Rows</th><th class="p-2 text-left">Status</th></tr></thead><tbody>
        @forelse($this->exportHistory() as $batch)
            <tr class="border-b"><td class="p-2">{{ $batch->id }}</td><td class="p-2">{{ $batch->record_count }}</td><td class="p-2">{{ $batch->status }}</td></tr>
        @empty <tr><td class="p-2" colspan="3">No exports yet.</td></tr> @endforelse
    </tbody></table></div>
    <div class="flex gap-2">
        <x-filament::button color="gray" :disabled="$data['page'] <= 1" wire:click="$set('pageNum', {{ max(1, $data['page'] - 1) }})">Previous</x-filament::button>
        <x-filament::button color="gray" :disabled="$data['page'] >= $data['maxPage']" wire:click="$set('pageNum', {{ min($data['maxPage'], $data['page'] + 1) }})">Next</x-filament::button>
    </div>
</x-filament-panels::page>
