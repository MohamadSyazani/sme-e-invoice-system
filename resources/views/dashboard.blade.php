@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h1 class="h3 mb-1">Welcome, {{ auth()->user()->name }}</h1>
    <p class="text-muted mb-4">You are signed in as <strong>{{ auth()->user()->role }}</strong>.</p>

    @if ($stats)
        {{-- OWNER view --}}
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Active staff</div>
                        <div class="display-6 fw-semibold">{{ $stats['activeStaff'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Inactive staff</div>
                        <div class="display-6 fw-semibold">{{ $stats['inactiveStaff'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Logins today</div>
                        <div class="display-6 fw-semibold">{{ $stats['loginsToday'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <a href="{{ route('reports.failed-logins') }}" class="text-decoration-none text-reset">
                    <div class="card shadow-sm h-100 {{ $stats['failedToday'] > 0 ? 'border-danger' : '' }}">
                        <div class="card-body">
                            <div class="text-muted small">Failed logins today</div>
                            <div class="display-6 fw-semibold {{ $stats['failedToday'] > 0 ? 'text-danger' : '' }}">{{ $stats['failedToday'] }}</div>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Recent logins</span>
                <a href="{{ route('reports.login-logs') }}" class="btn btn-sm btn-outline-secondary">Full report</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Staff</th><th>When</th><th>IP address</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($stats['recentLogins'] as $log)
                            <tr>
                                <td>{{ $log->user->name }}</td>
                                <td>{{ $log->login_at->format('d M Y, h:i A') }}</td>
                                <td class="text-muted">{{ $log->ip_address ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">No logins recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        {{-- CASHIER view: no financial or staff data is ever sent to this page. --}}
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">Your workspace</h2>
                <p class="text-muted mb-0">
                    POS Checkout, the Customer list and read-only product prices will appear in the
                    menu above as each module is connected.
                </p>
            </div>
        </div>
    @endif
@endsection
