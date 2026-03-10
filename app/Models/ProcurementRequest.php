<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcurementRequest extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Type constants.
     */
    public const TYPE_INVENTORY = 'inventory';
    public const TYPE_SERVICE = 'service';
    public const TYPE_EQUIPMENT_CHANGE = 'equipment_change';
    public const TYPE_CATALOG_CHANGE = 'catalog_change';

    /**
     * Change action constants.
     */
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';

    protected $fillable = [
        'department',
        'type',
        'requested_by',
        'approved_by',
        'status',
        'notes',
        'receipt_invoice_path',
        'received_at',
        'change_payload',
        'change_action',
        'target_model',
        'target_id',
    ];

    protected $casts = [
        'change_payload' => 'json',
        'received_at' => 'datetime',
    ];

    /**
     * Check if this is a change request (equipment or catalog).
     */
    public function isChangeRequest(): bool
    {
        return in_array($this->type, [self::TYPE_EQUIPMENT_CHANGE, self::TYPE_CATALOG_CHANGE]);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProcurementRequestItem::class);
    }
}
