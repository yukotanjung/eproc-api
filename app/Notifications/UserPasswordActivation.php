<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Crypt;

class UserPasswordActivation extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct($token, $email, $name, $callbackUrl)
    {
        $this->name = $name;
        $this->token = $token;
        $this->email = $email;
        $this->callback = $callbackUrl;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $email = Crypt::encrypt($this->email);
        $token = md5($this->token);
        $title = 'Account Activation';
        $url = $this->callback."?token=".$token."&email=".$email;

        return (new MailMessage())
            ->subject($title)
            ->line("Dear ".strtoupper($this->name).",")
            ->line('Welcome to '.config('app.name').'. This is your account activation link, please click button below to set you password and activate your account:')
            ->action('Verify your account', url($url))
            ->line('Your log in email is '.$this->email);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
