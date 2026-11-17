@extends('layouts.app')

@section('title', 'Failed Login Attempts')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-0">Failed login attempts</h1>
            <p class="text-muted small mb-0 d-none d-print-block">
                Generated {{ now()->format('d M Y, h:i A') }} by {{ auth()->user()->name }}
            </p>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm d-print-none" onclick="window.print()">Print</button>
    </div>

    @include('reports._tabs')

    <form method="GET" action="{{ route('reports.failed-logins') }}" class="card card-body shadow-sm mb-3 d-print-none">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="reason" class="form-label small mb-1">Reason</label>
                <select id="reason" name="reason" class="form-select @error('reason') is-invalid @enderror">
                    <option value="">All reasons</option>
                    @foreach ($reasons as $reason)
                        <option value="{{ $reason }}" @selected($filters['reason'] === $reason)>
                            {{ (new \App\Models\FailedLoginAttempt(['reason' => $reason]))->reason_label }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="from" class="form-label small mb-1">From date</label>
                <input type="date" id="from" name="from" value="{{ $filters['from'] }}"
                       class="form-control @error('from') is-invalid @enderror">
            </div>
            <div class="col-md-3">
                <label for="to" class="form-label small mb-1">To date</label>
                <input type="date" id="to" name="to" value="{{ $filters['to'] }}"
                       class="form-control @error('to') is-invalid @enderror">
                @error('to') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-secondary flex-fill">Apply</button>
                <a href="{{ route('reports.failed-logins') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
    </form>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Failed attempts</div>
                <div class="h3 mb-0">{{ number_format($summary['total']) }}</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Different IP addresses</div>
                <div class="h3 mb-0">{{ number_format($summary['ips']) }}</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Unregistered usernames tried</div>
                <div class="h3 mb-0">{{ number_format($summary['unknown']) }}</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Most recent attempt</div>
                <div class="h6 mb-0">{{ $summary['latest']?->format('d M Y, h:i A') ?? '-' }}</div>
            </div></div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Username typed</th>
                        <th>Reason</th>
                        <th>Date &amp; time</th>
                        <th>IP address</th>
                        <th>Browser / OS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($attempts as $attempt)
                        <tr>
                            <td class="text-muted">{{ $attempts->firstItem() + $loop->index }}</td>
                            <td>
                                {{ $attempt->username }}
                                @if ($attempt->user)
                                    <div class="text-muted small">{{ $attempt->user->name }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $attempt->reason === 'UNKNOWN_USERNAME' ? 'text-bg-secondary' : ($attempt->reason === 'WRONG_PASSWORD' ? 'text-bg-danger' : 'text-bg-warning') }}">
                                    {{ $attempt->reason_label }}
                                </span>
                            </td>
                            <td>{{ $attempt->attempted_at->format('d M Y, h:i:s A') }}</td>
                            <td>{{ $attempt->ip_address ?? '-' }}</td>
                            <td>{{ $attempt->browser }} <span class="text-muted">on {{ $attempt->platform }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No failed attempts match your filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3 d-print-none">
        {{ $attempts->links('pagination::bootstrap-5') }}
    </div>
@endsection
