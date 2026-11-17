<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Module 1 — "My Profile". Any logged-in staff member (Owner or Cashier)
 * can change their own name and password. Username, role and status stay under
 * the Owner's control.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->update($data);

        return redirect()->route('profile.edit')->with('success', 'Your name was updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ]);

        $user = $request->user();

        $user->update([
            'password' => $data['password'], // hashed by the User model's 'hashed' cast
            'must_change_password' => false,
        ]);

        // Rotate the session id after a credential change.
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Your password was changed.');
    }
}
