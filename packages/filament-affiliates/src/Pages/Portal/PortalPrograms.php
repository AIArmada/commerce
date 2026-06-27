<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Pages\Portal;

use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramCreative;
use AIArmada\Affiliates\Services\ProgramService;
use AIArmada\FilamentAffiliates\Concerns\PortalPage;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use RuntimeException;

class PortalPrograms extends PortalPage
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    /** @var view-string */
    protected string $view = 'filament-affiliates::pages.portal.programs';

    public static function getNavigationLabel(): string
    {
        return __('Programs');
    }

    public function getTitle(): string | Htmlable
    {
        return __('Programs & Assets');
    }

    public function joinProgram(string $programId): void
    {
        $affiliate = $this->getAffiliate();

        if (! $affiliate) {
            Notification::make()
                ->title(__('No Affiliate Account'))
                ->body(__('You do not have an affiliate account yet.'))
                ->danger()
                ->send();

            return;
        }

        $program = AffiliateProgram::query()->find($programId);

        if (! $program) {
            Notification::make()
                ->title(__('Program not found'))
                ->danger()
                ->send();

            return;
        }

        try {
            $membership = app(ProgramService::class)->joinProgram($affiliate, $program);
        } catch (RuntimeException) {
            Notification::make()
                ->title(__('Unable to join program'))
                ->body(__('You are not eligible to join this program at the moment.'))
                ->danger()
                ->send();

            return;
        }

        $title = $membership->status === MembershipStatus::Pending
            ? __('Program request submitted')
            : __('Joined program');

        Notification::make()
            ->title($title)
            ->body($membership->status === MembershipStatus::Pending
                ? __('Your request will be reviewed before access is granted.')
                : __('You now have access to the program assets.'))
            ->success()
            ->send();
    }

    public function leaveProgram(string $programId): void
    {
        $affiliate = $this->getAffiliate();

        if (! $affiliate) {
            Notification::make()
                ->title(__('No Affiliate Account'))
                ->body(__('You do not have an affiliate account yet.'))
                ->danger()
                ->send();

            return;
        }

        $program = AffiliateProgram::query()->find($programId);

        if (! $program) {
            Notification::make()
                ->title(__('Program not found'))
                ->danger()
                ->send();

            return;
        }

        app(ProgramService::class)->leaveProgram($affiliate, $program);

        Notification::make()
            ->title(__('Left program'))
            ->body(__('You have been removed from the program membership.'))
            ->success()
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $affiliate = $this->getAffiliate();

        if (! $affiliate) {
            return [
                'hasAffiliate' => false,
                'programs' => [],
                'creativeCount' => 0,
            ];
        }

        $programService = app(ProgramService::class);

        $programs = $programService->getAvailablePrograms()
            ->map(function (AffiliateProgram $program) use ($affiliate, $programService): array {
                $membership = $programService->getMembership($affiliate, $program);
                $isJoined = $membership?->status === MembershipStatus::Approved;
                $isPending = $membership?->status === MembershipStatus::Pending;

                return [
                    'id' => (string) $program->getKey(),
                    'name' => (string) $program->name,
                    'description' => $program->description,
                    'status' => $program->status->label(),
                    'requires_approval' => (bool) $program->requires_approval,
                    'membership_status' => $membership?->status->label(),
                    'is_joined' => $isJoined,
                    'is_pending' => $isPending,
                    'can_join' => $membership === null && $program->canJoin($affiliate),
                    'join_label' => $program->requires_approval ? __('Request Access') : __('Join Program'),
                    'leave_label' => __('Leave Program'),
                    'creative_count' => $isJoined ? $program->creatives->count() : 0,
                    'creatives' => $isJoined
                        ? $program->creatives->map(fn (AffiliateProgramCreative $creative): array => [
                            'id' => (string) $creative->getKey(),
                            'name' => (string) $creative->name,
                            'type' => (string) $creative->type,
                            'asset_url' => (string) $creative->asset_url,
                            'destination_url' => (string) $creative->destination_url,
                        ])->all()
                        : [],
                ];
            })
            ->values()
            ->all();

        $creativeCount = collect($programs)->sum(fn (array $program): int => (int) $program['creative_count']);

        return [
            'hasAffiliate' => true,
            'programs' => $programs,
            'creativeCount' => $creativeCount,
        ];
    }
}
