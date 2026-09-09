<?php

declare(strict_types=1);

namespace Modules\Auth\Notifications;

use App\Support\Queue\QueueName;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

final class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(#[\SensitiveParameter] string $token)
    {
        parent::__construct($token);

        $this->onQueue(QueueName::Mail->value);
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function toMail($notifiable): MailMessage
    {
        $broker = (string) config('auth.defaults.passwords');

        return (new MailMessage)
            ->subject('Đặt lại mật khẩu '.config('app.name'))
            ->view([
                'html' => 'auth::emails.reset-password',
                'text' => 'auth::emails.reset-password-text',
            ], [
                'appName' => (string) config('app.name'),
                'userName' => trim((string) ($notifiable->name ?? '')),
                'resetUrl' => $this->resetUrl($notifiable),
                'expiresInMinutes' => (int) config("auth.passwords.{$broker}.expire", 60),
            ]);
    }
}
