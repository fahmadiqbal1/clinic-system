<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalLab extends Model
{
    protected $fillable = [
        'name', 'short_name', 'city', 'contact_name', 'contact_phone',
        'contact_email', 'address', 'mou_commission_pct', 'pricing_notes',
        'mou_document_path', 'is_active',
    ];

    protected $casts = [
        'mou_commission_pct' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function referrals(): HasMany
    {
        return $this->hasMany(ExternalReferral::class);
    }
}
