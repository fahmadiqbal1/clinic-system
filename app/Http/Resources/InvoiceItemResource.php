<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'line_total' => (float) $this->line_total,
            'service_catalog' => $this->whenLoaded('serviceCatalog', fn () => $this->serviceCatalog ? [
                'id' => $this->serviceCatalog->id,
                'name' => $this->serviceCatalog->name,
                'department' => $this->serviceCatalog->department,
            ] : null),
        ];
    }
}
