<?php

namespace App\Http\Controllers;

use App\Models\StaffContract;
use App\Models\User;
use App\Services\PdfService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class StaffContractController extends Controller
{
    use AuthorizesRequests;

    /**
     * Authorize using StaffContractPolicy explicitly.
     */
    private function authorizeContract(string $ability, User $staff): void
    {
        $policy = app(\App\Policies\StaffContractPolicy::class);
        if (!$policy->before(Auth::user(), $ability) && !$policy->$ability(Auth::user(), $staff)) {
            abort(403);
        }
    }

    /**
     * Show active contract for a staff member (their own view, or Owner viewing someone's).
     */
    public function show(?User $staff = null): View
    {
        if (!$staff) {
            $staff = Auth::user();
        }

        $this->authorizeContract('viewContract', $staff);

        $contract = StaffContract::forUser($staff->id)
            ->where('status', 'active')
            ->latest('version')
            ->first();

        if (!$contract) {
            return view('contracts.no-contract', compact('staff'));
        }

        return view('contracts.show', compact('contract', 'staff'));
    }

    /**
     * List contracts. Owner/Receptionist without a staff param: show ALL contracts.
     * Staff members see their own contract history.
     */
    public function index(?User $staff = null): View
    {
        $user = Auth::user();

        // If no staff specified, auto-resolve
        if (!$staff) {
            if ($user->roles->pluck('name')->contains('Owner') || $user->roles->pluck('name')->contains('Receptionist')) {
                // Show all contracts for all staff
                $query = StaffContract::with('user', 'creator');

                if ($status = request('status')) {
                    $query->where('status', $status);
                }

                $contracts = $query->orderBy('created_at', 'desc')
                    ->paginate(20);

                return view('contracts.index', ['contracts' => $contracts, 'staff' => null]);
            }

            // Non-owner/receptionist: show own contracts
            $staff = $user;
        }

        $this->authorizeContract('viewContract', $staff);

        $contracts = StaffContract::forUser($staff->id)
            ->with('user', 'creator')
            ->orderBy('version', 'desc')
            ->paginate(20);

        return view('contracts.index', compact('contracts', 'staff'));
    }

    /**
     * Create new contract form (Owner only).
     */
    public function create(?User $staff = null): View
    {
        $this->authorize('createContract', StaffContract::class);

        if ($staff) {
            $this->authorizeContract('viewContract', $staff);
        } else {
            $staff = new User();
        }

        // All non-owner staff members
        $staffMembers = User::whereDoesntHave('roles', fn($q) => $q->where('name', 'Owner'))
            ->orderBy('name')
            ->get();

        return view('contracts.create', compact('staff', 'staffMembers'));
    }

    /**
     * Store new contract (Owner only).
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('createContract', StaffContract::class);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'contract_html' => 'required|string',
            'minimum_term_months' => 'required|integer|min:1',
            'effective_from' => 'required|date|after_or_equal:today',
        ]);

        $staff = User::find($validated['user_id']);
        if (!$staff || $staff->roles->pluck('name')->contains('Owner')) {
            return redirect()->back()->with('error', 'Cannot create a contract for an Owner account.');
        }

        // Supersede existing contracts
        $lastVersion = StaffContract::forUser($staff->id)
            ->orderBy('version', 'desc')
            ->value('version') ?? 0;

        $nextVersion = $lastVersion + 1;

        StaffContract::forUser($staff->id)
            ->where('status', 'active')
            ->update(['status' => 'superseded']);

        StaffContract::create([
            'user_id' => $staff->id,
            'version' => $nextVersion,
            'contract_html_snapshot' => $validated['contract_html'],
            'minimum_term_months' => (int) $validated['minimum_term_months'],
            'effective_from' => $validated['effective_from'],
            'status' => 'draft',
            'created_by' => Auth::user()->id,
        ]);

        return redirect()->route('contracts.show', ['staff' => $staff])
            ->with('success', 'Contract created successfully. Awaiting staff signature.');
    }

    /**
     * Sign contract (any staff member signs their own).
     */
    public function sign(StaffContract $contract): RedirectResponse
    {
        if ($contract->user_id !== Auth::user()->id) {
            abort(403, 'You can only sign your own contracts.');
        }

        if ($contract->status !== 'draft') {
            return redirect()->back()->with('error', 'Only draft contracts can be signed.');
        }

        $contract->update([
            'status' => 'active',
            'signed_at' => now(),
            'signed_ip' => request()->ip(),
            'signed_user_agent' => request()->header('User-Agent'),
        ]);

        return redirect()->route('contracts.show', ['staff' => $contract->user])
            ->with('success', 'Contract signed successfully.');
    }

    /**
     * View full contract document.
     */
    public function view(StaffContract $contract): View
    {
        $this->authorize('view', $contract);
        return view('contracts.view', compact('contract'));
    }

    /**
     * Submit resignation notice (any staff member for their own contract).
     */
    public function submitResignation(StaffContract $contract): RedirectResponse
    {
        if ($contract->user_id !== Auth::user()->id) {
            abort(403, 'You can only resign from your own contracts.');
        }

        if ($contract->status !== 'active') {
            return redirect()->back()->with('error', 'Only active contracts can have resignation notices.');
        }

        $contract->update([
            'resignation_notice_submitted_at' => now(),
        ]);

        return redirect()->route('contracts.show', ['staff' => $contract->user])
            ->with('success', 'Resignation notice submitted.');
    }

    /**
     * Mark early exit (Owner only).
     */
    public function markEarlyExit(StaffContract $contract): RedirectResponse
    {
        $this->authorize('updateContract', $contract);

        if ($contract->status !== 'active') {
            return redirect()->back()->with('error', 'Only active contracts can be marked for early exit.');
        }

        $contract->update([
            'early_exit_flag' => true,
        ]);

        return redirect()->route('contracts.show', ['staff' => $contract->user])
            ->with('success', 'Contract marked for early exit.');
    }

    /**
     * Download contract as a formatted PDF.
     */
    public function downloadPdf(StaffContract $contract, PdfService $pdfService)
    {
        return $pdfService->downloadContractPdf($contract);
    }
}
