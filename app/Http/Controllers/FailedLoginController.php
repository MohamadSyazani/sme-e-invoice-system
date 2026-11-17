<?php

namespace App\Http\Controllers;

use App\Models\FailedLoginAttempt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Module 1 — Failed Login Attempts (Security Audit). Owner only.
 */
class FailedLoginController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'reason' => ['nullable', Rule::in(FailedLoginAttempt::REASONS)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $filters = [
            'reason' => $validated['reason'] ?? null,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
        ];

        $attempts = $this->baseQuery($filters)
            ->with('user')
            ->latest('attempted_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $latest = $this->baseQuery($filters)->max('attempted_at');

        $summary = [
            'total' => $this->baseQuery($filters)->count(),
            'ips' => $this->baseQuery($filters)->whereNotNull('ip_address')->distinct()->count('ip_address'),
            'unknown' => $this->baseQuery($filters)
                ->where('reason', FailedLoginAttempt::REASON_UNKNOWN_USERNAME)->count(),
            'latest' => $latest ? Carbon::parse($latest) : null,
        ];

        return view('reports.failed-logins', [
            'attempts' => $attempts,
            'summary' => $summary,
            'filters' => $filters,
            'reasons' => FailedLoginAttempt::REASONS,
        ]);
    }

    /**
     * @param  array{reason: ?string, from: ?string, to: ?string}  $filters
     * @return Builder<FailedLoginAttempt>
     */
    private function baseQuery(array $filters): Builder
    {
        return FailedLoginAttempt::query()
            ->when($filters['reason'], fn ($q, $reason) => $q->where('reason', $reason))
            ->when($filters['from'], fn ($q, $from) => $q->whereDate('attempted_at', '>=', $from))
            ->when($filters['to'], fn ($q, $to) => $q->whereDate('attempted_at', '<=', $to));
    }
}
