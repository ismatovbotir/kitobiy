<?php

namespace App\Notifications;

use App\Models\LibraryInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email sent when an owner invites someone by email address. The recipient
 * usually has no account yet, so the mail goes to an on-demand route.
 */
class LibraryInvitationSent extends Notification
{
    use Queueable;

    public function __construct(public LibraryInvitation $invitation) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $replacements = [
            'inviter' => $this->invitation->inviter->name,
            'library' => $this->invitation->library->name,
        ];

        return (new MailMessage)
            ->subject(__(':inviter invites you to :library', $replacements))
            ->line(__(':inviter invites you to join the library “:library” on Kitobiy.', $replacements))
            ->action(__('Open invitation'), route('invitations.show', $this->invitation->token))
            ->line(__('The invitation expires on :date.', ['date' => $this->invitation->expires_at->toDateString()]));
    }
}
