<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AgentCallFailed extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */

    public $mobile;
    public $extension;
    public function __construct($mobile, $extension)
    {
        $this->mobile = $mobile;
        $this->extension = $extension;

    }


    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via($notifiable)
    {
        return ['database'];
    }


    /**
     * Get the mail representation of the notification.
     */
    public function toDatabase($notifiable)
    {
        return [
            'title' => "Call Failed",
            'body' => "Call to {$this->mobile} failed for agent {$this->extension}",
        ];
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
