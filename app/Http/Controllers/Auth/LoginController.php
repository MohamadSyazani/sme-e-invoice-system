<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\FailedLoginAttempt;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    /** This many failed attempts on one username within ATTEMPT_WINDOW seconds ... */
    private const MAX_ATTEMPTS = 5;

    private const ATTEMPT_WINDOW = 60;

    /** ... locks login for that username for this many seconds. */
    private const LOCKOUT_SECONDS = 60;

    /** Session key remembering which username is locked, so the countdown survives a page refresh. */
    private const LOCK_SESSION_KEY = 'login_lock_username';

    public function create(Request $request): View
    {
        $lockedUsername = $request->session()->get(self::LOCK_SESSION_KEY);
        $retryAfter = $lockedUsername ? $this->lockedSeconds($lockedUsername) : 0;

        if ($retryAfter === 0) {
            $request->session()->forget(self::LOCK_SESSION_KEY);
            $lockedUsername = null;
        }

        return view('auth.login', ['retryAfter' => $retryAfter, 'lockedUsername' => $lockedUsername]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $username = $this->normalise($credentials['username']);

        // This username is locked: do not even look at the password, just show the countdown again.
        if ($this->lockedSeconds($username) > 0) {
            return $this->lockedResponse($request, $username);
        }

        // Any other username is unaffected by someone else's lockout.
        $request->session()->forget(self::LOCK_SESSION_KEY);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            $account = User::where('username', $credentials['username'])->first();

            $this->recordFailure(
                $request,
                $credentials['username'],
                $account,
                $account ? FailedLoginAttempt::REASON_WRONG_PASSWORD : FailedLoginAttempt::REASON_UNKNOWN_USERNAME,
            );

            RateLimiter::hit($this->attemptsKey($username), self::ATTEMPT_WINDOW);

            // That was the last allowed attempt: start the lockout for this username.
            if (RateLimiter::attempts($this->attemptsKey($username)) >= self::MAX_ATTEMPTS) {
                RateLimiter::clear($this->attemptsKey($username));
                RateLimiter::hit($this->lockKey($username), self::LOCKOUT_SECONDS);

                return $this->lockedResponse($request, $username);
            }

            throw ValidationException::withMessages([
                'username' => 'These credentials do not match our records.',
            ]);
        }

        $user = Auth::user();

        // Correct password but deactivated account: refuse the session.
        // (Only revealed after a correct password, so it cannot be used to probe accounts.)
        if (! $user->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $this->recordFailure($request, $credentials['username'], $user, FailedLoginAttempt::REASON_INACTIVE_ACCOUNT);

            throw ValidationException::withMessages([
                'username' => 'Your account is inactive. Please contact the shop owner.',
            ]);
        }

        RateLimiter::clear($this->attemptsKey($username));
        $request->session()->regenerate();

        // Security audit trail: one row per successful login.
        $user->loginLogs()->create([
            'login_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'You have been logged out.');
    }

    private function lockedResponse(Request $request, string $username): RedirectResponse
    {
        $request->session()->put(self::LOCK_SESSION_KEY, $username);

        return redirect()->route('login')->withInput($request->only('username'));
    }

    /** Seconds left on this username's lockout, or 0 when it is not locked. */
    private function lockedSeconds(string $username): int
    {
        $key = $this->lockKey($username);

        return RateLimiter::tooManyAttempts($key, 1) ? RateLimiter::availableIn($key) : 0;
    }

    /** "Ali" and " ali " are the same account, so they share one counter. */
    private function normalise(string $username): string
    {
        return Str::lower(trim($username));
    }

    private function attemptsKey(string $username): string
    {
        return 'login-attempts:'.sha1($username);
    }

    private function lockKey(string $username): string
    {
        return 'login-lock:'.sha1($username);
    }

    /**
     * Security audit trail of failed logins. The typed password is never stored.
     */
    private function recordFailure(Request $request, string $username, ?User $account, string $reason): void
    {
        FailedLoginAttempt::create([
            'username' => Str::limit($username, 255, ''),
            'user_id' => $account?->id,
            'reason' => $reason,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'attempted_at' => now(),
        ]);
    }
}
