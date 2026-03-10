<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\InvoiceItemResource;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceController extends Controller
{
    /**
     * Display a listing of invoices.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $invoices = Invoice::query()
            ->when($request->department, function ($query, $department) {
                $query->where('department', $department);
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->patient_id, function ($query, $patientId) {
                $query->where('patient_id', $patientId);
            })
            // Scope based on user role
            ->when($user->hasRole('Doctor'), function ($query) use ($user) {
                $query->whereHas('patient', fn ($q) => $q->where('doctor_id', $user->id));
            })
            ->when($user->hasRole('Laboratory'), function ($query) {
                $query->where('department', 'lab');
            })
            ->when($user->hasRole('Radiology'), function ($query) {
                $query->where('department', 'radiology');
            })
            ->when($user->hasRole('Pharmacy'), function ($query) {
                $query->where('department', 'pharmacy');
            })
            ->with(['patient', 'prescribingDoctor'])
            ->latest()
            ->paginate($request->per_page ?? 15);

        return InvoiceResource::collection($invoices);
    }

    /**
     * Display the specified invoice.
     */
    public function show(Invoice $invoice): InvoiceResource
    {
        $this->authorize('view', $invoice);

        $invoice->load(['patient', 'items.serviceCatalog', 'prescribingDoctor', 'performer']);

        return new InvoiceResource($invoice);
    }

    /**
     * Get invoice items.
     */
    public function items(Invoice $invoice): AnonymousResourceCollection
    {
        $this->authorize('view', $invoice);

        $items = $invoice->items()->with('serviceCatalog')->get();

        return InvoiceItemResource::collection($items);
    }
}
