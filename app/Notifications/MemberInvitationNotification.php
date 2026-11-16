<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MemberInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $url, private readonly ?string $tenantName = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $account = $this->tenantName ? " to {$this->tenantName}" : '';

        return (new MailMessage)
            ->subject('You have been invited to Stockly')
            ->greeting('Welcome to Stockly')
            ->line("You have been invited{$account}.")
            ->action('Create your password', $this->url)
            ->line('This invitation expires in 24 hours and can only be used once.')
            ->line('If you were not expecting this invitation, you can ignore this email.');
    }
}
