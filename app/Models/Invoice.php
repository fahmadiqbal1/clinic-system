<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use App\Services\FinancialDistributionService;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | Allowed State Transitions
    |--------------------------------------------------------------------------
    */

    protected const ALLOWED_TRANSITIONS = [
        self::STATUS_PENDING     => [self::STATUS_IN_PROGRESS, self::STATUS_PAID, self::STATUS_CANCELLED],
        self::STATUS_IN_PROGRESS => [self::STATUS_COMPLETED, self::STATUS_PAID, self::STATUS_CANCELLED],
        self::STATUS_COMPLETED   => [self::STATUS_PAID],
        self::STATUS_PAID        => [],
        self::STATUS_CANCELLED   => [],
    ];

    /**
     * Fields that lab/rad staff may update on a paid invoice
     * (work-in-progress fields for the upfront-payment workflow).
     */
    protected const PAID_MUTABLE_FIELDS = [
        'report_text',
        'performed_by_user_id',
        'status',              // only for internal complete-and-distribute
        'distribution_snapshot',
        'lab_results',
        'radiology_images',
        'total_amount',        // pharmacy: totals computed after dispensing items
        'net_amount',          // pharmacy: net recalculated after dispensing
        'fbr_invoice_number',  // FBR: assigned after payment
        'fbr_status',          // FBR: submission status
        'fbr_submitted_at',    // FBR: submission timestamp
        'fbr_irn',             // FBR: Invoice Reference Number
        'fbr_qr_code',         // FBR: QR code data
        'fbr_invoice_seq',     // FBR: sequential invoice number
        'fbr_signature',       // FBR: digital signature
        'fbr_response',        // FBR: archived API response
    ];

    /*
    |--------------------------------------------------------------------------
    | Boot Guards (Hard Enforcement Layer)
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        static::updating(function (Invoice $invoice) {
            // Paid-invoice guard: only PAID_MUTABLE_FIELDS may be changed
            if ($invoice->getOriginal('status') === self::STATUS_PAID) {
                $dirty = array_keys($invoice->getDirty());
                $forbidden = array_diff($dirty, self::PAID_MUTABLE_FIELDS);

                if (!empty($forbidden)) {
                    throw new \RuntimeException(
                        'Paid invoices are immutable. Forbidden fields: ' . implode(', ', $forbidden)
                    );
                }
            }

            // Enforce strict state transitions
            if ($invoice->isDirty('status')) {
                $original = $invoice->getOriginal('status');
                $new      = $invoice->status;
                $allowed  = self::ALLOWED_TRANSITIONS[$original] ?? [];

                if (!in_array($new, $allowed, true)) {
                    throw new \RuntimeException(
                        "Invalid status transition from {$original} to {$new}."
                    );
                }
            }
        });

        // Prevent deletion of paid invoices (including soft delete)
        static::deleting(function (Invoice $invoice) {
            if ($invoice->status === self::STATUS_PAID) {
                throw new \RuntimeException('Paid invoices cannot be deleted.');
            }
        });
    }

    protected $fillable = [
        'patient_id',
        'patient_type',
        'department',
        'service_name',
        'total_amount',
        'discount_amount',
        'discount_by',
        'discount_reason',
        'discount_status',
        'discount_requested_by',
        'discount_requested_at',
        'discount_approved_by',
        'discount_approved_at',
        'net_amount',
        'prescribing_doctor_id',
        'has_prescribed_items',
        'referrer_name',
        'referrer_percentage',
        'status',
        'report_text',
        'payment_method',
        'paid_at',
        'paid_by',
        'created_by_user_id',
        'performed_by_user_id',
        'distribution_snapshot',
        'service_catalog_id',
        'prescription_id',
        'visit_id',
        'lab_results',
        'radiology_images',
        'fbr_invoice_number',
        'fbr_status',
        'fbr_submitted_at',
        'fbr_irn',
        'fbr_qr_code',
        'fbr_invoice_seq',
        'fbr_signature',
        'fbr_response',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'referrer_percentage' => 'decimal:2',
        'has_prescribed_items' => 'boolean',
        'paid_at' => 'datetime',
        'discount_requested_at' => 'datetime',
        'discount_approved_at' => 'datetime',
        'distribution_snapshot' => 'json',
        'lab_results' => 'json',
        'radiology_images' => 'json',
        'fbr_submitted_at' => 'datetime',
        'fbr_response' => 'json',
    ];

    /**
     * Discount workflow status constants.
     */
    public const DISCOUNT_NONE = 'none';
    public const DISCOUNT_PENDING = 'pending';
    public const DISCOUNT_APPROVED = 'approved';
    public const DISCOUNT_REJECTED = 'rejected';

    /**
     * Allowed status values.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Get allowed statuses.
     */
    public static function getAllowedStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_PAID,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * Get allowed transitions from current status.
     */
    public function getAllowedTransitions(): array
    {
        return self::ALLOWED_TRANSITIONS[$this->status] ?? [];
    }

    /**
     * Check if invoice can transition to target status.
     */
    public function canTransitionTo(string $targetStatus): bool
    {
        if (!in_array($targetStatus, self::getAllowedStatuses())) {
            return false;
        }

        return in_array($targetStatus, $this->getAllowedTransitions());
    }

    /**
     * Check if invoice is paid (final state).
     */
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Check if invoice can be edited (not cancelled).
     *
     * Paid invoices allow limited mutable fields (report_text, performed_by_user_id)
     * via PAID_MUTABLE_FIELDS guard in booted(), so editing is conceptually allowed.
     */
    public function canBeEdited(): bool
    {
        return $this->status !== self::STATUS_CANCELLED;
    }

    /*
    |--------------------------------------------------------------------------
    | Domain State Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Start work on invoice (pending → in_progress).
     *
     * For paid invoices (upfront workflow), this assigns the performer
     * without changing status — the invoice stays paid.
     */
    public function startWork(?int $performerId = null): bool
    {
        // Upfront-paid invoice: assign performer only, status stays paid
        if ($this->status === self::STATUS_PAID) {
            if ($performerId) {
                return $this->update(['performed_by_user_id' => $performerId]);
            }
            return true;
        }

        if ($this->status !== self::STATUS_PENDING) {
            throw new \RuntimeException('Only pending invoices can start work.');
        }

        $data = ['status' => self::STATUS_IN_PROGRESS];
        if ($performerId) {
            $data['performed_by_user_id'] = $performerId;
        }

        return $this->update($data);
    }

    /**
     * Mark invoice as completed (in_progress → completed).
     */
    public function markCompleted(): bool
    {
        if ($this->status !== self::STATUS_IN_PROGRESS) {
            throw new \RuntimeException('Only in-progress invoices can be completed.');
        }

        return $this->update(['status' => self::STATUS_COMPLETED]);
    }

    /**
     * Determine if lab/rad work has been completed on this paid invoice.
     *
     * Uses performer commission ledger entry as the definitive marker that
     * completeAndDistribute() was called (i.e. staff finished their work).
     * Falls back to checking report_text + performed_by_user_id if the
     * performer is salaried (no commission entry will be created).
     */
    public function isWorkCompleted(): bool
    {
        // Non-paid invoices: work is "done" if status is completed or beyond
        if (!$this->isPaid()) {
            return in_array($this->status, [self::STATUS_COMPLETED]);
        }

        // For paid invoices in lab/rad: check if performer commission was distributed
        // (meaning completeAndDistribute() was called)
        $hasPerformerCommission = $this->revenueLedgers()
            ->where('category', 'commission')
            ->whereNotNull('user_id')
            ->whereHas('user', fn ($q) => $q->where('id', $this->performed_by_user_id))
            ->exists();

        if ($hasPerformerCommission) {
            return true;
        }

        // For salaried performers (no commission entry created), check if
        // distributePerformerCommission() ran via the audit log
        if ($this->performed_by_user_id && $this->report_text) {
            return \App\Models\AuditLog::where('auditable_type', 'invoice')
                ->where('auditable_id', $this->id)
                ->where('action', 'performer_commission_added')
                ->exists();
        }

        return false;
    }

    /**
     * Mark invoice as paid.
     *
     * Accepts invoices in PENDING (upfront payment — lab/rad/consultation)
     * or COMPLETED (traditional post-work payment — pharmacy).
     *
     * Per-role enforcement:
     *   - Pharmacy invoices: Pharmacy or Owner
     *   - All others: Receptionist or Owner
     *
     * Financial distribution is triggered immediately only when
     * performed_by_user_id is already set (consultation, pharmacy).
     * For lab/rad (no performer yet), distribution is deferred
     * until completeAndDistribute() is called after work is done.
     */
    public function markPaid(string $paymentMethod, int $userId): bool
    {
        $payableStatuses = [self::STATUS_PENDING, self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED];

        if (!in_array($this->status, $payableStatuses, true)) {
            throw new \RuntimeException('Invoice must be pending, in progress, or completed before marking as paid.');
        }

        if ($this->isPaid()) {
            throw new \RuntimeException('Invoice is already paid.');
        }

        // Block payment while discount is pending approval
        if (($this->discount_status ?? self::DISCOUNT_NONE) === self::DISCOUNT_PENDING) {
            throw new \RuntimeException('Cannot mark invoice as paid while discount is pending approval.');
        }

        // Per-role domain enforcement
        $user = User::findOrFail($userId);

        $canMarkPaid = match ($this->department) {
            'pharmacy' => $user->hasRole('Pharmacy') || $user->hasRole('Owner'),
            default => $user->hasRole('Receptionist') || $user->hasRole('Owner'),
        };

        if (!$canMarkPaid) {
            $allowed = $this->department === 'pharmacy' ? 'Pharmacy or Owner' : 'Receptionist or Owner';
            throw new \RuntimeException("Only {$allowed} can mark {$this->department} invoices as paid.");
        }

        return DB::transaction(function () use ($paymentMethod, $userId) {
            // Compute net_amount before distributing
            $this->update([
                'status'         => self::STATUS_PAID,
                'payment_method' => $paymentMethod,
                'paid_at'        => now(),
                'paid_by'        => $userId,
                'net_amount'     => $this->total_amount - ($this->discount_amount ?? 0),
            ]);

            $fresh = $this->fresh();

            // Always distribute at payment time so doctor referral commissions
            // are visible immediately. For lab/rad upfront payments without a
            // performer, the performer commission will be added later via
            // completeAndDistribute() → distributePerformerCommission().
            (new FinancialDistributionService())->distribute($fresh);

            return true;
        });
    }

    /**
     * Complete work on a paid invoice and trigger deferred financial distribution.
     *
     * Used by lab/rad staff after filling in the report + performer on an
     * upfront-paid invoice. This finalises the revenue split now that the
     * performer (and therefore commission rate) is known.
     */
    public function completeAndDistribute(): bool
    {
        if ($this->status !== self::STATUS_PAID) {
            throw new \RuntimeException('completeAndDistribute() is only for paid invoices awaiting work completion.');
        }

        if (!$this->performed_by_user_id) {
            throw new \RuntimeException('performed_by_user_id must be set before completing work.');
        }

        if (!$this->report_text || empty(trim($this->report_text))) {
            throw new \RuntimeException('Report text must be saved before completing work.');
        }

        $service = new FinancialDistributionService();
        $fresh = $this->fresh();

        if ($fresh->revenueLedgers()->exists()) {
            // Distribution already happened at payment time.
            // Add performer commission now that performer is known.
            $service->distributePerformerCommission($fresh);
        } else {
            // Full distribution (fallback for legacy invoices paid without distribution)
            DB::transaction(fn () => $service->distribute($fresh));
        }

        return true;
    }

    /**
     * Request a discount (staff → Owner approval workflow).
     *
     * @param float       $amount      Proposed discount amount
     * @param int         $requesterId ID of the staff member requesting
     * @param string|null $reason      Business justification
     * @return bool
     */
    public function requestDiscount(float $amount, int $requesterId, ?string $reason = null): bool
    {
        if ($this->isPaid()) {
            throw new \RuntimeException('Cannot request discount on a paid invoice.');
        }

        if ($this->status === self::STATUS_CANCELLED) {
            throw new \RuntimeException('Cannot request discount on a cancelled invoice.');
        }

        if (($this->discount_status ?? self::DISCOUNT_NONE) === self::DISCOUNT_PENDING) {
            throw new \RuntimeException('A discount request is already pending approval.');
        }

        if ($amount <= 0) {
            throw new \RuntimeException('Discount amount must be positive.');
        }

        if ($amount > (float) $this->total_amount) {
            throw new \RuntimeException('Discount cannot exceed invoice total amount.');
        }

        $oldState = [
            'discount_amount' => $this->discount_amount ?? 0,
            'discount_status' => $this->discount_status ?? self::DISCOUNT_NONE,
        ];

        $this->update([
            'discount_amount' => $amount,
            'discount_reason' => $reason,
            'discount_status' => self::DISCOUNT_PENDING,
            'discount_requested_by' => $requesterId,
            'discount_requested_at' => now(),
            // Clear any previous approval data
            'discount_approved_by' => null,
            'discount_approved_at' => null,
        ]);

        AuditLog::create([
            'user_id' => $requesterId,
            'action' => 'discount_requested',
            'auditable_type' => self::class,
            'auditable_id' => $this->id,
            'before_state' => json_encode($oldState),
            'after_state' => json_encode([
                'discount_amount' => $amount,
                'discount_reason' => $reason,
                'discount_status' => self::DISCOUNT_PENDING,
            ]),
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);

        return true;
    }

    /**
     * Approve a pending discount request (Owner only).
     */
    public function approveDiscount(int $ownerId): bool
    {
        $user = User::findOrFail($ownerId);
        if (!$user->hasRole('Owner')) {
            throw new \RuntimeException('Only Owner can approve discounts.');
        }

        if (($this->discount_status ?? self::DISCOUNT_NONE) !== self::DISCOUNT_PENDING) {
            throw new \RuntimeException('No pending discount request to approve.');
        }

        $this->update([
            'discount_status' => self::DISCOUNT_APPROVED,
            'discount_by' => $ownerId,
            'discount_approved_by' => $ownerId,
            'discount_approved_at' => now(),
            'net_amount' => $this->total_amount - ($this->discount_amount ?? 0),
        ]);

        AuditLog::create([
            'user_id' => $ownerId,
            'action' => 'discount_approved',
            'auditable_type' => self::class,
            'auditable_id' => $this->id,
            'before_state' => json_encode(['discount_status' => self::DISCOUNT_PENDING]),
            'after_state' => json_encode([
                'discount_amount' => $this->discount_amount,
                'discount_status' => self::DISCOUNT_APPROVED,
            ]),
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);

        return true;
    }

    /**
     * Reject a pending discount request (Owner only).
     */
    public function rejectDiscount(int $ownerId, ?string $reason = null): bool
    {
        $user = User::findOrFail($ownerId);
        if (!$user->hasRole('Owner')) {
            throw new \RuntimeException('Only Owner can reject discounts.');
        }

        if (($this->discount_status ?? self::DISCOUNT_NONE) !== self::DISCOUNT_PENDING) {
            throw new \RuntimeException('No pending discount request to reject.');
        }

        $rejectedAmount = $this->discount_amount;

        $this->update([
            'discount_status' => self::DISCOUNT_REJECTED,
            'discount_amount' => 0,
            'net_amount' => $this->total_amount,
            'discount_reason' => $reason ?? $this->discount_reason,
            'discount_approved_by' => $ownerId,
            'discount_approved_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => $ownerId,
            'action' => 'discount_rejected',
            'auditable_type' => self::class,
            'auditable_id' => $this->id,
            'before_state' => json_encode([
                'discount_amount' => $rejectedAmount,
                'discount_status' => self::DISCOUNT_PENDING,
            ]),
            'after_state' => json_encode([
                'discount_amount' => 0,
                'discount_status' => self::DISCOUNT_REJECTED,
                'rejection_reason' => $reason,
            ]),
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);

        return true;
    }

    /**
     * Direct discount application by Owner (bypasses workflow for backward compat).
     *
     * @deprecated Use requestDiscount/approveDiscount workflow instead.
     */
    public function applyDiscount(float $amount, int $ownerId, ?string $reason = null): bool
    {
        if ($this->isPaid()) {
            throw new \RuntimeException('Cannot apply discount to a paid invoice.');
        }

        if (in_array($this->status, [self::STATUS_CANCELLED], true)) {
            throw new \RuntimeException('Cannot apply discount to a cancelled invoice.');
        }

        $user = User::findOrFail($ownerId);
        if (!$user->hasRole('Owner')) {
            throw new \RuntimeException('Only Owner can apply discounts.');
        }

        if ($amount <= 0) {
            throw new \RuntimeException('Discount amount must be positive.');
        }

        if ($amount > (float) $this->total_amount) {
            throw new \RuntimeException('Discount cannot exceed invoice total amount.');
        }

        $oldDiscount = $this->discount_amount ?? 0;

        $this->update([
            'discount_amount' => $amount,
            'discount_by' => $ownerId,
            'discount_reason' => $reason,
            'discount_status' => self::DISCOUNT_APPROVED,
            'discount_approved_by' => $ownerId,
            'discount_approved_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => $ownerId,
            'action' => 'discount_applied',
            'auditable_type' => self::class,
            'auditable_id' => $this->id,
            'before_state' => json_encode(['discount_amount' => $oldDiscount]),
            'after_state' => json_encode([
                'discount_amount' => $amount,
                'discount_by' => $ownerId,
                'discount_reason' => $reason,
            ]),
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);

        return true;
    }

    /**
     * Relationships
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function prescribingDoctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prescribing_doctor_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function revenueLedgers(): HasMany
    {
        return $this->hasMany(RevenueLedger::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function discountAppliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discount_by');
    }

    public function discountRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discount_requested_by');
    }

    public function discountApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discount_approved_by');
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function serviceCatalog(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalog::class);
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    /**
     * Get the effective revenue amount (after discount) for commission calculations.
     */
    public function getEffectiveRevenueAttribute(): float
    {
        return (float) ($this->net_amount ?? ($this->total_amount - ($this->discount_amount ?? 0)));
    }

    /**
     * Get total COGS from invoice items (for profit calculations).
     * Uses the stored line_cogs snapshot, falling back to cost_price * quantity.
     * Returns 0 for departments without items (profit = revenue).
     */
    public function getTotalCogsAttribute(): float
    {
        return (float) $this->items->sum(function ($item) {
            return $item->line_cogs ?? ($item->cost_price * $item->quantity);
        });
    }

    /**
     * Get profit (revenue after discount minus COGS).
     */
    public function getProfitAttribute(): float
    {
        return $this->effective_revenue - $this->total_cogs;
    }
}
