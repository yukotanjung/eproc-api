<?php

namespace App\Mail;

use URL;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;

class MailPasswordReset extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct($token, $email, $name, $callbackUrl)
    {
        $this->name = $name;
        $this->token = $token;
        $this->email = $email;
        $this->callback = $callbackUrl;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $address = 'support@inovasiadiwarna.com';
        $subject = 'Password Reset';
        $from = ucfirst(strtolower(config("app.name")));
        $token = md5($this->token);
        $email = Crypt::encrypt($this->email);

        $url = $this->callback . "?email=" . $email . "&token=" . $token;

        return $this->markdown('emails.PasswordReset')
            ->with([
                'name' => $this->name,
                'url' => $url,
                'email' => $email,
            ])
            ->from($address, $from)
            ->replyTo($this->email, strtoupper($this->name))
            ->subject($subject);
    }
}
