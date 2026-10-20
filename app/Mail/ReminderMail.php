<?php

namespace App\Mail;

use App\Models\Reminder;
use Illuminate\Mail\Mailable;

class ReminderMail extends Mailable
{
    public function __construct(public Reminder $reminder)
    {
    }

    public function build(): self
    {
        $html = '<p>'.e($this->reminder->message).'</p>';
        if ($this->reminder->whatsapp_url) {
            $html .= '<p>WhatsApp: <a href="'.e($this->reminder->whatsapp_url).'">'.e($this->reminder->whatsapp_url).'</a></p>';
        }

        return $this->subject($this->reminder->title)->html($html);
    }
}
