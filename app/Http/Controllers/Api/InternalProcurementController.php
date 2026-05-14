<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Internal endpoint called by the sidecar Ops AI tool (make_draft_procurement_tool).
 * Protected by sidecar JWT — never exposed directly to browser clients.
 *
 * POST /api/internal/procurement/draft
 * Authorization: Bearer <CLINIC_SIDECAR_JWT>
 */
class InternalProcurementController extends Controller
{
    public function draft(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'quantity'          => ['required', 'integer', 'min:1', 'max:10000'],
            'reason'            => ['required', 'string', 'max:500'],
        ]);

        $item = InventoryItem::findOrFail($validated['inventory_item_id']);

        // Deduplicate: skip if a pending_approval draft already exists for this item
        $existing = ProcurementRequest::where('status', 'pending_approval')
            ->whereHas('items', fn ($q) => $q->where('inventory_item_id', $item->id))
            ->exists();

        if ($existing) {
            return response()->json([
                'created' => false,
                'message' => "Pending draft already exists for {$item->name}.",
            ]);
        }

        DB::transaction(function () use ($validated, $item) {
            $pr = ProcurementRequest::create([
                'department'          => $item->department ?? 'pharmacy',
                'status'              => 'pending_approval',
                'type'                => 'standard',
                'requested_by'        => null, // AI-originated
                'ai_approved_at'      => null,
                'ai_approval_reason'  => $validated['reason'],
                'notes'               => 'Auto-drafted by Ops AI. Awaiting owner approval.',
            ]);

            ProcurementRequestItem::create([
                'procurement_request_id' => $pr->id,
                'inventory_item_id'      => $item->id,
                'item_name'              => $item->name,
                'quantity_requested'     => $validated['quantity'],
                'unit'                   => $item->unit ?? 'units',
                'notes'                  => $validated['reason'],
            ]);

            Log::channel('single')->info('ops_ai_draft_procurement', [
                'item_id'   => $item->id,
                'item_name' => $item->name,
                'quantity'  => $validated['quantity'],
            ]);
        });

        return response()->json(['created' => true, 'item' => $item->name], 201);
    }
}
