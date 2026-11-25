<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Portal\EntryPoints\Filament\Pages;

use AIArmada\Affiliates\Exceptions\ProgramEnrollmentDenied;
use AIArmada\Affiliates\Features\Programs\Api\PortalProgramRecord;
use AIArmada\Affiliates\Features\Programs\Api\ProgramEnrollment;
use AIArmada\Affiliates\Features\Programs\Api\ProgramPortalReader;
use AIArmada\FilamentAffiliates\Features\Portal\EntryPoints\Filament\Concerns\PortalPage;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

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

        if (! app(ProgramPortalReader::class)->programExists($programId)) {
            Notification::make()
                ->title(__('Program not found'))
                ->danger()
                ->send();

            return;
        }

        try {
            $membership = app(ProgramEnrollment::class)->join((string) $affiliate->getKey(), $programId);
        } catch (ProgramEnrollmentDenied) {
            Notification::make()
                ->title(__('Unable to join program'))
                ->body(__('You are not eligible to join this program at the moment.'))
                ->danger()
                ->send();

            return;
        }

        if (! $membership->active && ! $membership->isPending()) {
            Notification::make()
                ->title(__('Membership is not active'))
                ->warning()
                ->send();

            return;
        }

        $title = $membership->isPending()
            ? __('Program request submitted')
            : __('Joined program');

        Notification::make()
            ->title($title)
            ->body($membership->isPending()
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

        if (! app(ProgramPortalReader::class)->programExists($programId)) {
            Notification::make()
                ->title(__('Program not found'))
                ->danger()
                ->send();

            return;
        }

        app(ProgramEnrollment::class)->leave((string) $affiliate->getKey(), $programId);

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

        $records = app(ProgramPortalReader::class)->programs((string) $affiliate->getKey());

        $programs = array_map(static fn (PortalProgramRecord $program): array => [
            'id' => $program->id,
            'name' => $program->name,
            'description' => $program->description,
            'status' => $program->statusLabel,
            'requires_approval' => $program->requiresApproval,
            'membership_status' => $program->membershipStatusLabel,
            'is_joined' => $program->activeMembership,
            'is_pending' => $program->isPending(),
            'can_join' => $program->canJoin,
            'join_label' => $program->requiresApproval ? __('Request Access') : __('Join Program'),
            'leave_label' => __('Leave Program'),
            'creative_count' => count($program->creatives),
            'creatives' => $program->creatives,
        ], $records);

        $creativeCount = array_sum(array_column($programs, 'creative_count'));

        return [
            'hasAffiliate' => true,
            'programs' => $programs,
            'creativeCount' => $creativeCount,
        ];
    }
}
