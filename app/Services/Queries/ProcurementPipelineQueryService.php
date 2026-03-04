<?php

namespace App\Services\Queries;

use App\Models\ProcurementRequest;
use Illuminate\Support\Collection;

class ProcurementPipelineQueryService
{
    /**
     * Get procurement requests by status
     */
    public function getByStatus(string $status): Collection
    {
        return ProcurementRequest::where('status', $status)
            ->with('requester', 'items.inventoryItem')
            ->latest()
            ->get()
            ->map($this->formatRequest(...));
    }

    /**
     * Get pending inventory procurements
     */
    public function getPendingInventory(): Collection
    {
        return ProcurementRequest::where('status', 'pending')
            ->where('type', 'inventory')
            ->with('requester', 'items.inventoryItem')
            ->latest()
            ->get()
            ->map($this->formatRequest(...));
    }

    /**
     * Get pending service procurements
     */
    public function getPendingService(): Collection
    {
        return ProcurementRequest::where('status', 'pending')
            ->where('type', 'service')
            ->with('requester', 'items')
            ->latest()
            ->get()
            ->map($this->formatRequest(...));
    }

    /**
     * Get approved inventory procurements (awaiting receipt)
     */
    public function getApprovedInventory(): Collection
    {
        return ProcurementRequest::where('status', 'approved')
            ->where('type', 'inventory')
            ->with('requester', 'approver', 'items.inventoryItem')
            ->latest()
            ->get()
            ->map($this->formatRequest(...));
    }

    /**
     * Get approved service procurements
     */
    public function getApprovedService(): Collection
    {
        return ProcurementRequest::where('status', 'approved')
            ->where('type', 'service')
            ->with('requester', 'approver', 'items')
            ->latest()
            ->get()
            ->map($this->formatRequest(...));
    }

    /**
     * Get received procurements
     */
    public function getReceived(): Collection
    {
        return ProcurementRequest::where('status', 'received')
            ->where('type', 'inventory')
            ->with('requester', 'approver', 'items.inventoryItem')
            ->latest()
            ->get()
            ->map($this->formatRequest(...));
    }

    /**
     * Get procurements by department
     */
    public function getByDepartment(string $department): Collection
    {
        return ProcurementRequest::where('department', $department)
            ->with('requester', 'approver', 'items.inventoryItem')
            ->latest()
            ->get()
            ->map($this->formatRequest(...));
    }

    /**
     * Get pipeline summary
     */
    public function getPipelineSummary(): array
    {
        return [
            'pending_inventory' => ProcurementRequest::where('status', 'pending')
                ->where('type', 'inventory')
                ->count(),
            'pending_service' => ProcurementRequest::where('status', 'pending')
                ->where('type', 'service')
                ->count(),
            'approved_inventory' => ProcurementRequest::where('status', 'approved')
                ->where('type', 'inventory')
                ->count(),
            'approved_service' => ProcurementRequest::where('status', 'approved')
                ->where('type', 'service')
                ->count(),
            'received' => ProcurementRequest::where('status', 'received')
                ->where('type', 'inventory')
                ->count(),
            'rejected' => ProcurementRequest::where('status', 'rejected')
                ->count(),
        ];
    }

    /**
     * Format request for display
     */
    private function formatRequest(ProcurementRequest $request): array
    {
        return [
            'id' => $request->id,
            'department' => $request->department,
            'type' => $request->type,
            'status' => $request->status,
            'requested_by' => $request->requester->name,
            'requested_at' => $request->created_at->format('Y-m-d H:i'),
            'approved_by' => $request->approver?->name,
            'approved_at' => $request->updated_at->format('Y-m-d H:i'),
            'item_count' => $request->items->count(),
            'notes' => $request->notes,
        ];
    }
}
