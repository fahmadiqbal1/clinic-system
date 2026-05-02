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
    public const TYPE_INVENTORY        = 'inventory';
    public const TYPE_SERVICE          = 'service';
    public const TYPE_EQUIPMENT_CHANGE = 'equipment_change';
    public const TYPE_CATALOG_CHANGE   = 'catalog_change';
    public const TYPE_PRICE_LIST       = 'price_list';
    public const TYPE_NEW_ITEM_REQUEST = 'new_item_request';

    /**
     * Change action constants.
     */
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';

    protected $fillable = [
        'department',
        'type',
        'vendor_id',
        'requested_by',
        'approved_by',
        'status',
        'po_dispatch_status',
        'po_sent_at',
        'notes',
        'receipt_invoice_path',
        'price_list_path',
        'price_list_diff',
        'received_at',
        'change_payload',
        'change_action',
        'target_model',
        'target_id',
        'ai_approved_at',
        'ai_approval_reason',
        'receipt_deadline_at',
        'receipt_overdue_notified_at',
        'checklist_date',
        'checklist_supplier',
    ];

    protected $casts = [
        'change_payload'               => 'json',
        'price_list_diff'              => 'json',
        'received_at'                  => 'datetime',
        'po_sent_at'                   => 'datetime',
        'ai_approved_at'               => 'datetime',
        'receipt_deadline_at'          => 'datetime',
        'receipt_overdue_notified_at'  => 'datetime',
        'checklist_date'               => 'date',
    ];

    /**
     * Check if this is a change request (equipment or catalog).
     */
    public function isChangeRequest(): bool
    {
        return in_array($this->type, [self::TYPE_EQUIPMENT_CHANGE, self::TYPE_CATALOG_CHANGE]);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
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
