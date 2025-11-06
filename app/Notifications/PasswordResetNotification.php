<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class PasswordResetNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $token,
        public string $email
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $resetUrlBase = config('app.url');
        $frontendUrl = env('RESET_PASSWORD_URL');
        $url = ($frontendUrl ?: $resetUrlBase) . '/reset-password?token=' . urlencode($this->token) . '&email=' . urlencode($this->email);

        return (new MailMessage)
            ->subject('Recuperación de contraseña')
            ->view('emails.password_reset', [
                'resetUrl' => $url,
                'expiresHours' => 24,
            ]);
    }
}