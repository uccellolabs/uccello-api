<?php

namespace Uccello\Api\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class SyncErrorNotification extends Notification
{
    use Queueable;

    protected $moduleName;
    protected $error;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($moduleName, $error)
    {
        $this->moduleName = $moduleName;
        $this->error = $error;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject(trans('uccello-api:error.exception.email.title'))
            ->line(trans('uccello-api:error.exception.email.message'))
            ->line($this->error);
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
