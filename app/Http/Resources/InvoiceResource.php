<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'department' => $this->department,
            'service_name' => $this->service_name,
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id,
                'full_name' => $this->patient->full_name,
            ]),
            'prescribing_doctor' => $this->whenLoaded('prescribingDoctor', fn () => $this->prescribingDoctor ? [
                'id' => $this->prescribingDoctor->id,
                'name' => $this->prescribingDoctor->name,
            ] : null),
            'performer' => $this->whenLoaded('performer', fn () => $this->performer ? [
                'id' => $this->performer->id,
                'name' => $this->performer->name,
            ] : null),
            'total_amount' => (float) $this->total_amount,
            'discount_amount' => (float) $this->discount_amount,
            'net_amount' => (float) $this->net_amount,
            'discount_status' => $this->discount_status,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'paid_at' => $this->paid_at?->toISOString(),
            'has_prescribed_items' => $this->has_prescribed_items,
            'items' => $this->whenLoaded('items', fn () => InvoiceItemResource::collection($this->items)),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
