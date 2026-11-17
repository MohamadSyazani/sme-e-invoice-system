<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\UpdateStaffRequest;
use App\Models\FailedLoginAttempt;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Module 1 — Staff CRUD. Owner only (enforced in routes/web.php).
 */
class StaffController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $staff = User::query()
            ->when($search !== '', fn ($query) => $query->where(
                fn ($w) => $w->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
            ))
            ->when(in_array($request->query('role'), User::ROLES, true),
                fn ($query) => $query->where('role', $request->query('role')))
            ->when(in_array($request->query('status'), User::STATUSES, true),
                fn ($query) => $query->where('status', $request->query('status')))
            ->withCount('loginLogs')
            ->withMax('loginLogs as last_login_at', 'login_at')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('staff.index', ['staff' => $staff]);
    }

    public function create(): View
    {
        return view('staff.create');
    }

    public function store(StoreStaffRequest $request): RedirectResponse
    {
        // The password is hashed automatically by the User model's 'hashed' cast.
        // The Owner chose this password, so the staff member must replace it on first login.
        $user = User::create($request->validated() + ['must_change_password' => true]);

        return redirect()->route('staff.index')
            ->with('success', "Staff account for {$user->name} was created.");
    }

    public function edit(User $user): View
    {
        return view('staff.edit', ['staff' => $user]);
    }

    public function update(UpdateStaffRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']); // keep the existing password
        } else {
            // An Owner resetting someone else's password forces a new one on next login.
            // An Owner choosing their own password does not.
            $data['must_change_password'] = ! $user->is($request->user());
        }

        $user->update($data);

        return redirect()->route('staff.index')
            ->with('success', "{$user->name}'s account was updated.");
    }

    /** Quick Activate / Deactivate button on the staff list. */
    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $user->update([
            'status' => $user->isActive() ? User::STATUS_INACTIVE : User::STATUS_ACTIVE,
        ]);

        return back()->with('success', $user->isActive()
            ? "{$user->name} was activated."
            : "{$user->name} was deactivated and can no longer log in.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        // Same principle as Soft-Archiving: accounts with history are kept for the audit trail.
        if ($user->hasHistory()) {
            return back()->with('error',
                "{$user->name} has login history and cannot be deleted. Deactivate the account instead.");
        }

        // Keep the failed-login history but detach it from the deleted account.
        FailedLoginAttempt::where('user_id', $user->id)->update(['user_id' => null]);

        $user->delete();

        return redirect()->route('staff.index')
            ->with('success', "{$user->name}'s account was deleted.");
    }
}
