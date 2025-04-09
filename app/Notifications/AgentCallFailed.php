<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AgentCallFailed extends Notification
{
    use Queueable;

    public $extension;
    public $mobile;

    public function __construct($extension, $mobile)
    {
        $this->extension = $extension;
        $this->mobile = $mobile;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'Call Failed',
            'body' => "Call to {$this->mobile} failed for agent {$this->extension}",
        ];
    }
}
