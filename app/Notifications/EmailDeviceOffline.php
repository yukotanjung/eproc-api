<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class EmailDeviceOffline extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct($devices, $name)
    {
        $this->devices = $devices;
        $this->name = $name;
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
        $table = "<table border='1' style='border-collapse:collapse;width:100%'><tr style='text-align:center;font-weight:bold'><td>Name</td><td>Serial</td><td>Last Online</td></tr>";
        foreach($this->devices as $ls){
            $lastOnline = date("d F Y",strtotime($ls->last_online));
            $table .= "<tr><td>$ls->name<br>$ls->description</td><td>$ls->serial</td><td>$lastOnline</td></tr>";
        }
        $table .= "</table>";

        return (new MailMessage)
            ->subject('Moove Media Digital Taxi Topper Device Offline')
            ->from('support@inovasiadiwarna.com', 'Moove Media')
            ->line(new HtmlString('<div style="text-align:center;margin-bottom:40px;"><img src="' . asset('assets/images/mm-sg-logo.jpg') .'" class="logo" alt="'.config('app.name').'"></div>'))
            ->line(new HtmlString("Dear <strong>".ucwords($this->name)."</strong>,"))
            ->line("We would like to notify you that your devices has been offline for over two days.")
            ->line(new HtmlString($table))
            ->line(new HtmlString('<br>'))
            ->line("If you have any questions or need additional information, feel free to contact our support team.")
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
