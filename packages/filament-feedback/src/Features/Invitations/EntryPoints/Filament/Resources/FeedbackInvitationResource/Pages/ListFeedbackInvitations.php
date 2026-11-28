<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Features\Invitations\EntryPoints\Filament\Resources\FeedbackInvitationResource\Pages;

use AIArmada\FilamentFeedback\Features\Invitations\EntryPoints\Filament\Resources\FeedbackInvitationResource;
use Filament\Resources\Pages\ListRecords;

final class ListFeedbackInvitations extends ListRecords
{
    protected static string $resource = FeedbackInvitationResource::class;
}
