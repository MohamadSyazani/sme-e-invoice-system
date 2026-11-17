<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserLoginLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Module 1 — System Login Activity (Security Audit) Report. Owner only.
 */
class LoginLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        $logs = $this->baseQuery($filters)
            ->with('user')
            ->latest('login_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $latest = $this->baseQuery($filters)->max('login_at');

        $summary = [
            'total' => $this->baseQuery($filters)->count(),
            'staff' => $this->baseQuery($filters)->distinct()->count('user_id'),
            'latest' => $latest ? Carbon::parse($latest) : null,
        ];

        return view('reports.login-logs', [
            'logs' => $logs,
            'summary' => $summary,
            'filters' => $filters,
            'staffList' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Download the (filtered) report as a CSV file that opens in Excel. */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $filename = 'login-activity-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($filters) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows names correctly

            fputcsv($out, ['No.', 'Staff Name', 'Username', 'Role', 'Login Date & Time', 'IP Address', 'Browser', 'Operating System']);

            $n = 0;
            $this->baseQuery($filters)->with('user')->chunkByIdDesc(500, function ($logs) use ($out, &$n) {
                foreach ($logs as $log) {
                    fputcsv($out, array_map($this->csvSafe(...), [
                        ++$n,
                        $log->user->name,
                        $log->user->username,
                        $log->user->role,
                        $log->login_at->format('Y-m-d H:i:s'),
                        $log->ip_address ?? '-',
                        $log->browser,
                        $log->platform,
                    ]));
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{user_id: ?int, from: ?string, to: ?string}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return [
            'user_id' => $validated['user_id'] ?? null,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
        ];
    }

    /**
     * @param  array{user_id: ?int, from: ?string, to: ?string}  $filters
     * @return Builder<UserLoginLog>
     */
    private function baseQuery(array $filters): Builder
    {
        return UserLoginLog::query()
            ->when($filters['user_id'], fn ($q, $id) => $q->where('user_id', $id))
            ->when($filters['from'], fn ($q, $from) => $q->whereDate('login_at', '>=', $from))
            ->when($filters['to'], fn ($q, $to) => $q->whereDate('login_at', '<=', $to));
    }

    /** Stop spreadsheet formula injection from a malicious staff name (=, +, -, @). */
    private function csvSafe(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
