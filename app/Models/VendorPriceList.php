<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorPriceList extends Model
{
    protected $fillable = [
        'vendor_id',
        'external_lab_id',
        'uploaded_by',
        'filename',
        'original_filename',
        'file_path',
        'file_type',
        'status',
        'extracted_at',
        'applied_at',
        'applied_by',
        'extraction_summary',
        'flag_reasons',
        'item_count',
        'flagged_count',
        'applied_count',
    ];

    protected $casts = [
        'extracted_at'       => 'datetime',
        'applied_at'         => 'datetime',
        'extraction_summary' => 'array',
        'flag_reasons'       => 'array',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(VendorPriceItem::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function externalLab(): BelongsTo
    {
        return $this->belongsTo(ExternalLab::class);
    }

    /**
     * Whether this price list is ready for human review.
     */
    public function getIsReviewableAttribute(): bool
    {
        return in_array($this->status, ['extracted', 'flagged'], true);
    }
}
