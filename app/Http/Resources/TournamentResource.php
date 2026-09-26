<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TournamentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'venue' => $this->venue,
            'starts_on' => $this->starts_on->format('Y-m-d'),
            'ends_on' => $this->ends_on?->format('Y-m-d'),
            'timezone' => $this->timezone, 'status' => $this->status,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
