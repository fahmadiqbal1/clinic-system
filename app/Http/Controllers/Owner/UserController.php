<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Mail\UserCreatedMail;
use App\Models\User;
use App\Services\AuditableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * List all users for Owner management.
     */
    public function index(Request $request): View
    {
        $status = $request->query('status', 'active');
        $isActive = $status !== 'inactive';

        $users = User::with('roles')
            ->where('is_active', $isActive)
            ->orderly()
            ->paginate(25)
            ->appends(['status' => $status]);

        $activeCount = User::where('is_active', true)->count();
        $inactiveCount = User::where('is_active', false)->count();
        $roles = Role::all();

        return view('owner.users.index', [
            'users' => $users,
            'roles' => $roles,
            'status' => $status,
            'activeCount' => $activeCount,
            'inactiveCount' => $inactiveCount,
        ]);
    }

    /**
     * Show form to create new user.
     */
    public function create(): View
    {
        $roles = Role::all();

        return view('owner.users.create', [
            'roles' => $roles,
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'role_id' => ['required', 'exists:roles,id'],
            'compensation_type' => ['required', 'in:salaried,commission,hybrid'],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'commission_consultation' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_pharmacy' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_lab' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_radiology' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_independent' => ['nullable', 'boolean'],
        ]);

        // Force commission rates to zero for salaried users
        $isSalaried = $validated['compensation_type'] === 'salaried';

        // Assign role first so we can enforce is_independent only for Doctor
        $role = Role::find($validated['role_id']);
        $isIndependent = ($role->name === 'Doctor') && !empty($validated['is_independent']);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_active' => true,
            'is_independent' => $isIndependent,
            'compensation_type' => $validated['compensation_type'],
            'base_salary' => $validated['base_salary'] ?? null,
            'commission_consultation' => $isSalaried ? 0 : ($validated['commission_consultation'] ?? null),
            'commission_pharmacy' => $isSalaried ? 0 : ($validated['commission_pharmacy'] ?? null),
            'commission_lab' => $isSalaried ? 0 : ($validated['commission_lab'] ?? null),
            'commission_radiology' => $isSalaried ? 0 : ($validated['commission_radiology'] ?? null),
        ]);
        $user->assignRole($role);

        AuditableService::logUserRoleChange($user, null, $role->name);

        // Send welcome email with credentials
        try {
            Mail::to($user->email)->send(new UserCreatedMail($user, $validated['password'], $role->name));
        } catch (\Exception $e) {
            Log::warning('Failed to send welcome email to ' . $user->email . ': ' . $e->getMessage());
        }

        return redirect()->route('owner.users.index')
            ->with('success', "User {$user->name} created and assigned role {$role->name}");
    }

    /**
     * Show form to edit user.
     */
    public function edit(User $user): View
    {
        $roles = Role::all();
        $userRole = $user->roles()->first();

        return view('owner.users.edit', [
            'user' => $user,
            'roles' => $roles,
            'userRole' => $userRole,
        ]);
    }

    /**
     * Update user details and role.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        // Prevent self-deactivation
        if ($request->input('is_active') === '0' && Auth::id() === $user->id) {
            return redirect()->back()
                ->withErrors('You cannot deactivate your own account.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'role_id' => ['required', 'exists:roles,id'],
            'is_active' => ['boolean'],
            'is_independent' => ['nullable', 'boolean'],
            'compensation_type' => ['required', 'in:salaried,commission,hybrid'],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'commission_consultation' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_pharmacy' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_lab' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_radiology' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $oldRole = $user->roles()->first()?->name;
        $newRole = Role::findOrFail($validated['role_id']);
        $newIsActive = $request->has('is_active');
        $oldIsActive = $user->is_active;

        // Force commission rates to zero for salaried users
        $isSalaried = $validated['compensation_type'] === 'salaried';

        // is_independent only applies to Doctor role
        $isIndependent = ($newRole->name === 'Doctor') && !empty($validated['is_independent']);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_active' => $newIsActive,
            'is_independent' => $isIndependent,
            'compensation_type' => $validated['compensation_type'],
            'base_salary' => $validated['base_salary'] ?? null,
            'commission_consultation' => $isSalaried ? 0 : ($validated['commission_consultation'] ?? null),
            'commission_pharmacy' => $isSalaried ? 0 : ($validated['commission_pharmacy'] ?? null),
            'commission_lab' => $isSalaried ? 0 : ($validated['commission_lab'] ?? null),
            'commission_radiology' => $isSalaried ? 0 : ($validated['commission_radiology'] ?? null),
        ]);

        // Update role - remove all and assign new
        $user->syncRoles([$newRole]);

        // Log role change if different
        if ($oldRole !== $newRole->name) {
            AuditableService::logUserRoleChange($user, $oldRole, $newRole->name);
        }

        // Log activation change if different
        if ($oldIsActive !== $newIsActive) {
            AuditableService::logUserActivityChange($user, $newIsActive);
        }

        return redirect()->route('owner.users.index')
            ->with('success', "User {$user->name} updated successfully");
    }
}
