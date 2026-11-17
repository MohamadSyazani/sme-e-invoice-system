@extends('layouts.app')

@section('title', 'Login Activity Report')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-0">Login activity report</h1>
            <p class="text-muted small mb-0 d-none d-print-block">
                Generated {{ now()->format('d M Y, h:i A') }} by {{ auth()->user()->name }}
            </p>
        </div>
        <div class="d-flex gap-2 d-print-none">
            <a href="{{ route('reports.login-logs.export', array_filter($filters)) }}"
               class="btn btn-outline-success btn-sm">Export CSV</a>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">Print</button>
        </div>
    </div>

    @include('reports._tabs')

    <form method="GET" action="{{ route('reports.login-logs') }}" class="card card-body shadow-sm mb-3 d-print-none">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="user_id" class="form-label small mb-1">Staff member</label>
                <select id="user_id" name="user_id" class="form-select @error('user_id') is-invalid @enderror">
                    <option value="">All staff</option>
                    @foreach ($staffList as $member)
                        <option value="{{ $member->id }}" @selected((string) $filters['user_id'] === (string) $member->id)>
                            {{ $member->name }}
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
                <a href="{{ route('reports.login-logs') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
    </form>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Total logins</div>
                <div class="h3 mb-0">{{ number_format($summary['total']) }}</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Different staff who logged in</div>
                <div class="h3 mb-0">{{ number_format($summary['staff']) }}</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Most recent login</div>
                <div class="h5 mb-0">{{ $summary['latest']?->format('d M Y, h:i A') ?? '-' }}</div>
            </div></div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Staff</th>
                        <th>Role</th>
                        <th>Login date &amp; time</th>
                        <th>IP address</th>
                        <th>Browser / OS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="text-muted">{{ $logs->firstItem() + $loop->index }}</td>
                            <td>
                                {{ $log->user->name }}
                                <div class="text-muted small">{{ $log->user->username }}</div>
                            </td>
                            <td>
                                <span class="badge {{ $log->user->isOwner() ? 'text-bg-warning' : 'text-bg-info' }}">
                                    {{ $log->user->role }}
                                </span>
                            </td>
                            <td>{{ $log->login_at->format('d M Y, h:i:s A') }}</td>
                            <td>{{ $log->ip_address ?? '-' }}</td>
                            <td>{{ $log->browser }} <span class="text-muted">on {{ $log->platform }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No login records match your filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3 d-print-none">
        {{ $logs->links('pagination::bootstrap-5') }}
    </div>
@endsection
