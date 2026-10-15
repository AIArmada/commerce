<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Pages;

use AIArmada\Addressing\ReferenceSync\DiffEngine;
use AIArmada\Addressing\Jobs\ReferenceSyncJob;
use AIArmada\Addressing\ReferenceSync\ReferenceArchive;
use AIArmada\Addressing\ReferenceSync\ReferenceReview;
use AIArmada\Addressing\ReferenceSync\ReferenceWriter;
use AIArmada\Addressing\ReferenceSync\SnapshotRepository;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ReferenceSyncPage extends Page
{
    protected string $view = 'filament-addressing::pages.reference-sync';

    protected static ?string $title = 'Geography Contributions & Updates';

    protected static ?string $slug = 'geography-contributions';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?int $navigationSort = 98;

    public string $direction = 'contribution';

    public string $filter = 'pending';

    public int $pageNum = 1;

    public static function getNavigationGroup(): ?string
    {
        return config('filament-addressing.navigation.group', 'Addressing');
    }

    public static function canAccess(): bool
    {
        // Explicit opt-in gate; no implicit access merely for being a Filament user.
        return (bool) config('filament-addressing.features.reference_sync', false)
            && auth()->check()
            && auth()->user()->can('addressing.manage-reference-sync');
    }

    public function updatedDirection(): void { $this->pageNum = 1; }

    public function updatedFilter(): void { $this->pageNum = 1; }

    public function rows(): array
    {
        abort_unless(self::canAccess(), 403);
        $q = DB::table(DiffEngine::TABLE)->where('direction', $this->direction);
        if ($this->filter !== 'all') {
            $q->where('decision', $this->filter);
        }
        $total = (clone $q)->count();
        $maxPage = max(1, (int) ceil($total / 20));
        $this->pageNum = min(max(1, $this->pageNum), $maxPage);
        return ['total' => $total, 'page' => $this->pageNum, 'maxPage' => $maxPage,
            'rows' => $q->orderBy('id')->forPage($this->pageNum, 20)->get()];
    }

    public function refreshComparison(): void
    {
        abort_unless(self::canAccess(), 403);
        $this->queueOperation('compare');
    }

    public function loadBundledRelease(): void
    {
        abort_unless(self::canAccess(), 403);
        $this->queueOperation('load');
    }

    public function initializeBaseline(): void
    {
        abort_unless(self::canAccess(), 403);
        $this->queueOperation('baseline');
    }

    public function review(int $id, string $decision): void
    {
        abort_unless(self::canAccess(), 403);
        (new ReferenceReview)->decide($id, $decision, (string) auth()->id());
        Notification::make()->title('Review decision saved')->success()->send();
    }

    public function exportApproved(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        abort_unless(self::canAccess(), 403);
        $directory = storage_path('app/private/addressing/contributions');
        $path = $directory . '/contributions-' . Str::uuid() . '.zip';
        (new ReferenceArchive)->exportContributions($path);
        return response()->download($path, basename($path))->deleteFileAfterSend();
    }

    public function applyApproved(): void
    {
        abort_unless(self::canAccess(), 403);
        $this->queueOperation('apply');
    }

    private function queueOperation(string $operation): void
    {
        ReferenceSyncJob::dispatch($operation)->onQueue((string) config('filament-addressing.reference_sync.queue', 'default'));
        Notification::make()->title('Reference data job queued')
            ->body('Refresh the page to see completion status.')->success()->send();
    }

    public function exportHistory(): array
    {
        abort_unless(self::canAccess(), 403);
        return DB::table('addressing_reference_exports')->orderByDesc('exported_at')->limit(8)->get()->all();
    }

    public function releaseInfo(): array
    {
        $repo = new SnapshotRepository;
        return [
            'baseline' => $repo->get('baseline_revision', 'Not initialized'),
            'incoming' => $repo->get('remote_revision', 'Not loaded'),
            'job' => $repo->get('job', null),
        ];
    }

}
