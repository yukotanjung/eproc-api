<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorEmailVerification extends Notification
{
    use Queueable;

    protected string $token;
    protected string $email;
    protected string $name;
    protected ?string $callbackUrl;

    public function __construct(string $token, string $email, string $name, ?string $callbackUrl = null)
    {
        $this->token = $token;
        $this->email = $email;
        $this->name = $name;
        $this->callbackUrl = $callbackUrl;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $baseUrl = $this->callbackUrl ?: route('vendor.verify');
        $separator = str_contains($baseUrl, '?') ? '&' : '?';
        $url = $baseUrl . $separator . http_build_query([
            'token' => $this->token,
            'email' => $this->email,
        ]);

        return (new MailMessage())
            ->subject('Verify Your Vendor Account')
            ->line('Dear ' . strtoupper($this->name) . ',')
            ->line('Thank you for registering at ' . config('app.name') . '. Please click the button below to verify your email and activate your account:')
            ->action('Verify Email & Activate', $url)
            ->line('Your login email is ' . $this->email);
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
