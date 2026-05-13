<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifies an invited user with an activation link.
 *
 * Mail channel is the only channel used here.
 * In development, configure MAIL_MAILER=log and the message will appear
 * in storage/logs/laravel.log. In production, set MAIL_MAILER=smtp
 * (or ses / resend / postmark) with the corresponding MAIL_* env vars.
 *
 * The notification is queued so the HTTP response is not held while
 * the mail transport is contacted. The Redis queue worker (sm_queue)
 * will process it in the background.
 */
class UserInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $activationUrl,
        private readonly string $invitedByName,
        private readonly string $roleName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Invitación al Portal SM — Strategic Mates')
            ->greeting('¡Hola, '.$notifiable->name.'!')
            ->line("{$this->invitedByName} te invitó a unirte al Portal SM como {$this->roleName}.")
            ->action('Activar mi cuenta', $this->activationUrl)
            ->line('Este enlace es válido por 7 días.')
            ->line('Si no esperabas esta invitación, puedes ignorar este correo de forma segura.');
    }

    /**
     * Array representation (useful for database channel if added later).
     */
    public function toArray(object $notifiable): array
    {
        return [
            'activation_url' => $this->activationUrl,
            'invited_by' => $this->invitedByName,
            'role' => $this->roleName,
        ];
    }
}
