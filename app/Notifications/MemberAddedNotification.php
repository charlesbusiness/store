<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MemberAddedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Tenant $tenant) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("You have been added to {$this->tenant->name}")
            ->greeting('Account access granted')
            ->line("You have been added to {$this->tenant->name} on Stockly.")
            ->line('Sign in with your existing password to continue.')
            ->line('If you were not expecting this, contact the account owner.');
    }
}
