<?php

namespace App\Notifications;

use URL;
use Illuminate\Bus\Queueable;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class EmailVerification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct($token, $email, $name, $userId, $callBackUrl)
    {
        $this->name = $name;
        $this->token = $token;
        $this->email = $email;
        $this->callback = $callBackUrl;
        $this->userId = $userId;
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
        $address = config('mail.from.address');
        $token = md5($this->token);
        $callback = $this->callback;
        $uid = Crypt::encrypt($this->userId);
        $title = 'Moove Media Digital Taxi Topper Email Verification';
        $url = $callback."?email=$email&uid=$uid&token=$token";
        return (new MailMessage())
            ->subject($title)
            ->from($address, 'Moove Media Digital Taxi Topper')
            ->line(new HtmlString('<div style="text-align:center;margin-bottom:40px;"><img src="' . asset('assets/images/mm-sg-logo.jpg') .'" class="logo" alt="'.config('app.name').'"></div>'))
            ->line(new HtmlString("Dear <strong>".ucwords($this->name)."</strong>,"))
            ->line("You recently requested to change the email address associated with your Moove Media Digital Taxi Topper account. Please verify your new email address by clicking the button below:")
            ->action('Verify your account', url($url))
            ->line('If you did not request this change, please contact our support team immediately.')
            ->line('Thank you for keeping your account information up to date.')
            ->salutation("Best Regards");
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
