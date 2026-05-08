<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExternalLab extends Model
{
    protected $fillable = [
        'name', 'short_name', 'city', 'contact_name', 'contact_phone',
        'contact_email', 'address', 'mou_commission_pct', 'pricing_notes',
        'mou_document_path', 'is_active', 'vendor_id',
    ];

    protected $casts = [
        'mou_commission_pct' => 'decimal:2',
        'is_active'          => 'boolean',
    ];

    public function portalUser(): HasOne
    {
        return $this->hasOne(User::class, 'external_lab_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(ExternalReferral::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function testPrices(): HasMany
    {
        return $this->hasMany(ExternalLabTestPrice::class);
    }

    public function priceLists(): HasMany
    {
        return $this->hasMany(VendorPriceList::class);
    }
}
