<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class EmailChange extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct($name, $email)
    {
        $this->name = $name;
        $this->email = $email;
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
        return (new MailMessage)
            ->subject(ucfirst(strtolower(config("app.name"))).' Email Address Change Request')
            ->from('support@inovasiadiwarna.com', ucfirst(strtolower(config("app.name"))))
            ->line(new HtmlString('<div style="text-align:center;margin-bottom:40px;"><img src="' . asset('assets/images/mm-sg-logo.png') .'" class="logo" alt="'.config('app.name').'"></div>'))
            ->line(new HtmlString("Dear <strong>".ucwords($this->name)."</strong>,"))
            ->line("We've noticed you've recently changed the email for your account. To confirm the change, please check $this->email to verify this address and complete updating your account.")
            ->line('If you did not request change email, you can safely ignore this email. If you need any further assistance, feel free to contact our support team.')
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
