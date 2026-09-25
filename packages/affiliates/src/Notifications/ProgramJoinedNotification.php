<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Notifications;

use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ProgramJoinedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly AffiliateProgram $program,
        private readonly AffiliateProgramMembership $membership,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $approved = $this->membership->status === MembershipStatus::Approved;

        return (new MailMessage)
            ->subject($approved
                ? "You're in: {$this->program->name}"
                : "Application received: {$this->program->name}")
            ->line($approved
                ? "Your application to {$this->program->name} was approved. Your tracking links are ready."
                : "Your application to {$this->program->name} is pending review. We'll notify you once approved.");
    }
}
