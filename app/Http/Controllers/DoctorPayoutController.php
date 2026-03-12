<?php

namespace App\Http\Controllers;

use App\Models\DoctorPayout;
use App\Models\RevenueLedger;
use App\Models\User;
use App\Notifications\PayoutDecision;
use App\Services\DoctorPayoutService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DoctorPayoutController extends Controller
{
    use AuthorizesRequests;

    protected DoctorPayoutService $payoutService;

    public function __construct(DoctorPayoutService $payoutService)
    {
        $this->payoutService = $payoutService;
    }

    /**
     * Show payout generation form — loads all staff with unpaid totals
     */
    public function create(Request $request): View
    {
        $this->authorize('create', DoctorPayout::class);

        // All non-owner staff who have at least one unpaid commission
        $staffMembers = User::whereDoesntHave('roles', fn ($q) => $q->where('name', 'Owner'))
            ->whereHas('roles') // must have a role
            ->orderBy('name')
            ->get()
            ->map(function ($user) {
                $user->unpaid_total = (float) RevenueLedger::where('user_id', $user->id)
                    ->where('category', 'commission')
                    ->whereNull('payout_id')
                    ->sum('amount');
                return $user;
            });

        $preselectedStaffId = $request->query('doctor_id');

        return view('payouts.create', compact('staffMembers', 'preselectedStaffId'));
    }

    /**
     * Store a new payout — no period selection, grabs all unpaid entries
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', DoctorPayout::class);

        $validated = $request->validate([
            'staff_id' => 'required|exists:users,id',
            'paid_amount' => 'required|numeric|min:0.01',
            'salary_amount' => 'nullable|numeric|min:0',
        ]);

        try {
            $staff = User::findOrFail($validated['staff_id']);

            if ($staff->hasRole('Owner')) {
                return redirect()->back()->withErrors(['staff_id' => 'Cannot create payouts for owner accounts.']);
            }

            // Doctor → daily commission payout (no approval needed)
            // Other staff → monthly payout with salary (needs owner approval)
            if ($staff->hasRole('Doctor')) {
                $payout = $this->payoutService->generatePayout(
                    $staff,
                    null,
                    null,
                    (float) $validated['paid_amount'],
                    Auth::user()
                );
            } else {
                $payout = $this->payoutService->generateMonthlyPayout(
                    $staff,
                    null,
                    null,
                    (float) $validated['paid_amount'],
                    Auth::user(),
                    isset($validated['salary_amount']) ? (float) $validated['salary_amount'] : null
                );
            }

            return redirect()->route('reception.payouts.show', $payout)->with('success', 'Payout generated successfully');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['staff_id' => $e->getMessage()]);
        }
    }

    /**
     * Show payout details
     */
    public function show(DoctorPayout $payout): View
    {
        $this->authorize('view', $payout);

        $payout->load('doctor', 'creator', 'confirmer', 'approver', 'revenueLedgers');

        return view('payouts.show', compact('payout'));
    }

    /**
     * List payouts (filtered by role)
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', DoctorPayout::class);

        $query = DoctorPayout::with('doctor', 'creator', 'confirmer');

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Role-based scope
        if ($user->hasRole('Doctor')) {
            $query->where('doctor_id', Auth::id());
        } elseif ($user->hasRole('Receptionist')) {
            $query->where('created_by', Auth::id());
        }
        // Owner sees all — no scope restriction

        // Filter by staff member (Owner filter)
        if ($request->filled('staff_id')) {
            $query->where('doctor_id', $request->input('staff_id'));
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by date range
        if ($request->filled('from')) {
            $query->whereDate('period_start', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('period_end', '<=', $request->input('to'));
        }

        $payouts = $query->orderBy('created_at', 'desc')->paginate(20);

        // For owner filter dropdowns
        $staffList = $user->hasRole('Owner')
            ? User::whereDoesntHave('roles', fn ($q) => $q->where('name', 'Owner'))
                ->whereHas('roles')
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        return view('payouts.index', compact('payouts', 'staffList'));
    }

    /**
     * Confirm a payout (staff member acknowledges receipt)
     */
    public function confirm(DoctorPayout $payout): RedirectResponse
    {
        if ($payout->doctor_id !== Auth::id()) {
            abort(403, 'You can only confirm your own payouts');
        }

        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $this->payoutService->confirmPayout(
                $payout,
                $user,
                request()->ip(),
                request()->header('User-Agent')
            );

            return redirect()->route('reception.payouts.show', $payout)->with('success', 'Payout confirmed successfully');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Approve a monthly payout (Owner action)
     */
    public function approve(DoctorPayout $payout): RedirectResponse
    {
        $this->authorize('approve', $payout);

        try {
            $this->payoutService->approvePayout($payout, Auth::user());

            // Notify the staff member
            $payout->doctor?->notify(new PayoutDecision($payout, PayoutDecision::DECISION_APPROVED));

            return redirect()->route('reception.payouts.show', $payout)->with('success', 'Payout approved successfully');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Reject a monthly payout (Owner action)
     */
    public function reject(Request $request, DoctorPayout $payout): RedirectResponse
    {
        $this->authorize('approve', $payout);

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $reason = $validated['reason'] ?? null;
            $this->payoutService->rejectPayout($payout, Auth::user(), $reason);

            // Notify the staff member
            $payout->doctor?->notify(new PayoutDecision($payout, PayoutDecision::DECISION_REJECTED, $reason));

            return redirect()->route('reception.payouts.show', $payout)->with('success', 'Payout rejected — commission entries released');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withError('Error: ' . $e->getMessage());
        }
    }

    /**
     * Create correction (Owner only)
     */
    public function createCorrection(DoctorPayout $payout): View
    {
        $this->authorize('createCorrection', $payout);

        return view('payouts.correction-create', compact('payout'));
    }

    /**
     * Store correction
     */
    public function storeCorrection(Request $request, DoctorPayout $payout): RedirectResponse
    {
        $this->authorize('createCorrection', $payout);

        $validated = $request->validate([
            'amount' => 'required|numeric',
            'reason' => 'required|string|max:500',
        ]);

        try {
            $correction = $this->payoutService->createCorrection(
                $payout,
                (float) $validated['amount'],
                Auth::user(),
                $validated['reason']
            );

            return redirect()->route('reception.payouts.show', $correction)->with('success', 'Correction created successfully');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withError('Error: ' . $e->getMessage());
        }
    }
}
