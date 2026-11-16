<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetOtpNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $otp) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Stockly password reset code')
            ->greeting('Password reset requested')
            ->line("Your one-time password reset code is: {$this->otp}")
            ->line('This code expires in 10 minutes and can only be used once.')
            ->line('If you did not request this, you can ignore this email.');
    }
}
