<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'card_number' => $this->card_number,
            'chart_number' => $this->card_number,
            'name' => $this->name,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'email' => $this->email,
            'is_first_visit' => (bool) $this->is_first_visit,
            'has_rehab_clearance' => (bool) $this->has_rehab_clearance,
            'is_diagnosed' => (bool) $this->is_diagnosed,
            'can_book_rehab' => $this->canBookRehab(),
            'assigned_therapist_id' => $this->assigned_therapist_id,
            'assigned_therapist' => $this->whenLoaded('assignedTherapist'),
            'reservations' => $this->whenLoaded('appointments'),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
