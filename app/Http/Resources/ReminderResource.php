<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReminderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'audience' => $this->audience,
            'title' => $this->title,
            'message' => $this->message,
            'due_at' => optional($this->due_at)?->toDateString(),
            'status' => $this->status,
            'email' => $this->email,
            'whatsapp' => $this->whatsapp,
            'whatsapp_url' => $this->whatsapp_url,
            'channel' => $this->channel,
            'cliente' => $this->whenLoaded('cliente', fn () => $this->cliente?->displayName()),
            'sent_at' => optional($this->sent_at)?->toDateTimeString(),
        ];
    }
}
