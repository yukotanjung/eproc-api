<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\HtmlString;

class EmailForgotPassword extends Notification
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
        $token = md5($this->token);
        $email = Crypt::encrypt($this->email);
        $url = $this->callback . "?email=" . $email . "&token=" . $token;

        return (new MailMessage)
            ->subject('Set Your New '.ucfirst(strtolower(config("app.name"))).' Dashboard Account Password')
            ->from('support@inovasiadiwarna.com', ucfirst(strtolower(config("app.name"))))
            ->line(new HtmlString('<div style="text-align:center;margin-bottom:40px;"><img src="' . asset('assets/images/mm-sg-logo.png') .'" class="logo" alt="'.config('app.name').'"></div>'))
            ->line(new HtmlString("Dear <strong>".ucwords($this->name)."</strong>,"))
            ->line("We received a request to reset the password for your account associated with this email address. If you made this request, please follow the instructions below.")
            ->action('Reset Password', $url)
            ->line('If you did not request a password reset, you can safely ignore this email. If you need any further assistance, feel free to contact our support team.')
            ->line(new HtmlString('<br>'))
            ->line(new HtmlString('<br>'))
            ->line(new HtmlString('<br>'))
            ->line('Best regards,')
            ->line(new HtmlString('<br>'))
            ->salutation(config('app.name') . ' Support Team');
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
