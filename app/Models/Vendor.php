<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    protected $fillable = [
        'name', 'short_name', 'contact_name', 'email', 'phone',
        'address', 'payment_terms', 'po_email', 'notes',
        'auto_send_po', 'is_approved',
        'last_checklist_date', 'checklist_valid_until',
    ];

    protected $casts = [
        'auto_send_po'         => 'boolean',
        'is_approved'          => 'boolean',
        'last_checklist_date'  => 'date',
        'checklist_valid_until' => 'date',
    ];

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function procurementRequests(): HasMany
    {
        return $this->hasMany(ProcurementRequest::class);
    }
}
