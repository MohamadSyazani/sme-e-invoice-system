<?php

namespace App\Http\Controllers;

use App\Models\FailedLoginAttempt;
use App\Models\User;
use App\Models\UserLoginLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $stats = null;

        // Owner-only numbers: a Cashier never receives them.
        if ($request->user()->isOwner()) {
            $stats = [
                'activeStaff' => User::active()->count(),
                'inactiveStaff' => User::where('status', User::STATUS_INACTIVE)->count(),
                'loginsToday' => UserLoginLog::whereDate('login_at', today())->count(),
                'failedToday' => FailedLoginAttempt::whereDate('attempted_at', today())->count(),
                'recentLogins' => UserLoginLog::with('user')
                    ->latest('login_at')
                    ->latest('id')
                    ->limit(5)
                    ->get(),
            ];
        }

        return view('dashboard', ['stats' => $stats]);
    }
}
