<?php

namespace App\Http\Resources;

use App\Services\CashSessionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $expected = $this->expected_amount;
        if ($this->closed_at === null) {
            $expected = app(CashSessionService::class)->expectedAmount($this->resource);
        }

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'cajero' => $this->whenLoaded('user', fn () => $this->user->name),
            'opened_at' => optional($this->opened_at)?->toDateTimeString(),
            'closed_at' => optional($this->closed_at)?->toDateTimeString(),
            'opening_amount' => $this->opening_amount,
            'expected_amount' => $expected,
            'counted_amount' => $this->counted_amount,
            'difference' => $this->difference,
            'open' => $this->closed_at === null,
        ];
    }
}
