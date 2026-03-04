<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{
    /**
     * Global search for command palette — returns patients, invoices, users.
     */
    public function global(Request $request): JsonResponse
    {
        $q = trim($request->query('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $results = [];
        $like = "%{$q}%";

        // Search patients
        $patients = Patient::where('name', 'LIKE', $like)
            ->orWhere('phone', 'LIKE', $like)
            ->orWhere('id_number', 'LIKE', $like)
            ->limit(5)
            ->get(['id', 'name', 'phone']);

        foreach ($patients as $p) {
            $results[] = [
                'icon' => 'bi-person',
                'title' => $p->name,
                'subtitle' => 'Patient • ' . ($p->phone ?? 'No phone'),
                'url' => route('receptionist.patients.show', $p->id),
            ];
        }

        // Search invoices by ID or patient name
        $invoices = Invoice::with('patient')
            ->where('id', 'LIKE', $like)
            ->orWhereHas('patient', fn ($pq) => $pq->where('name', 'LIKE', $like))
            ->limit(5)
            ->get(['id', 'patient_id', 'department', 'status', 'total_amount']);

        foreach ($invoices as $inv) {
            $results[] = [
                'icon' => 'bi-receipt',
                'title' => 'Invoice #' . $inv->id,
                'subtitle' => ($inv->patient?->name ?? 'Unknown') . ' • ' . ucfirst($inv->department) . ' • ' . ucfirst($inv->status),
                'url' => route('receptionist.invoices.show', $inv->id),
            ];
        }

        // Search staff (Owner only)
        /** @var \App\Models\User|null $authUser */
        $authUser = Auth::user();
        if ($authUser && $authUser->hasRole('Owner')) {
            $users = User::where('name', 'LIKE', $like)
                ->orWhere('email', 'LIKE', $like)
                ->limit(3)
                ->get(['id', 'name', 'email']);

            foreach ($users as $u) {
                $results[] = [
                    'icon' => 'bi-person-badge',
                    'title' => $u->name,
                    'subtitle' => 'Staff • ' . $u->email,
                    'url' => route('owner.users.edit', $u->id),
                ];
            }
        }

        return response()->json(['results' => array_slice($results, 0, 10)]);
    }
}
