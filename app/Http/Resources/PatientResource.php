<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'age' => $this->date_of_birth?->age,
            'status' => $this->status,
            'registration_type' => $this->registration_type,
            'doctor' => $this->whenLoaded('doctor', fn () => [
                'id' => $this->doctor->id,
                'name' => $this->doctor->name,
            ]),
            'registered_at' => $this->registered_at?->toISOString(),
            'triage_started_at' => $this->triage_started_at?->toISOString(),
            'doctor_started_at' => $this->doctor_started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'triage_vitals' => $this->whenLoaded('triageVitals', fn () => $this->triageVitals->map(fn ($v) => [
                'id' => $v->id,
                'blood_pressure' => $v->blood_pressure,
                'temperature' => $v->temperature,
                'pulse_rate' => $v->pulse_rate,
                'oxygen_saturation' => $v->oxygen_saturation,
                'priority' => $v->priority,
                'created_at' => $v->created_at->toISOString(),
            ])),
            'prescriptions' => $this->whenLoaded('prescriptions', fn () => $this->prescriptions->map(fn ($p) => [
                'id' => $p->id,
                'diagnosis' => $p->diagnosis,
                'status' => $p->status,
                'created_at' => $p->created_at->toISOString(),
            ])),
        ];
    }
}
