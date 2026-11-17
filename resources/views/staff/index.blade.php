@extends('layouts.app')

@section('title', 'Staff')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Staff accounts</h1>
        <a href="{{ route('staff.create') }}" class="btn btn-primary">Add staff</a>
    </div>

    <form method="GET" action="{{ route('staff.index') }}" class="card card-body shadow-sm mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label for="q" class="form-label small mb-1">Search</label>
                <input type="search" id="q" name="q" value="{{ request('q') }}" class="form-control"
                       placeholder="Name or username">
            </div>
            <div class="col-md-3">
                <label for="role" class="form-label small mb-1">Role</label>
                <select id="role" name="role" class="form-select">
                    <option value="">All roles</option>
                    @foreach (\App\Models\User::ROLES as $role)
                        <option value="{{ $role }}" @selected(request('role') === $role)>{{ ucfirst(strtolower($role)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label small mb-1">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">All</option>
                    @foreach (\App\Models\User::STATUSES as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(strtolower($status)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-secondary flex-fill">Filter</button>
                <a href="{{ route('staff.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last login</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($staff as $member)
                        @php $isSelf = $member->is(auth()->user()); @endphp
                        <tr>
                            <td>
                                {{ $member->name }}
                                @if ($isSelf) <span class="badge text-bg-secondary">You</span> @endif
                            </td>
                            <td>{{ $member->username }}</td>
                            <td>
                                <span class="badge {{ $member->isOwner() ? 'text-bg-warning' : 'text-bg-info' }}">
                                    {{ $member->role }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $member->isActive() ? 'text-bg-success' : 'text-bg-danger' }}">
                                    {{ $member->status }}
                                </span>
                                @if ($member->must_change_password)
                                    <span class="badge text-bg-warning" title="Must choose a new password at next login">Password pending</span>
                                @endif
                            </td>
                            <td class="text-muted small">
                                {{ $member->last_login_at?->format('d M Y, h:i A') ?? 'Never' }}
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('staff.edit', $member) }}" class="btn btn-sm btn-outline-primary">Edit</a>

                                @unless ($isSelf)
                                    <form method="POST" action="{{ route('staff.toggle-status', $member) }}" class="d-inline">
                                        @csrf @method('PATCH')
                                        <button type="submit"
                                                class="btn btn-sm {{ $member->isActive() ? 'btn-outline-warning' : 'btn-outline-success' }}">
                                            {{ $member->isActive() ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>

                                    @if ($member->login_logs_count === 0)
                                        <form method="POST" action="{{ route('staff.destroy', $member) }}" class="d-inline"
                                              onsubmit="return confirm('Delete {{ addslashes($member->name) }}? This cannot be undone.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    @else
                                        <button type="button" class="btn btn-sm btn-outline-danger" disabled
                                                title="Has login history — deactivate instead">Delete</button>
                                    @endif
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No staff accounts match your filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $staff->links('pagination::bootstrap-5') }}
    </div>
@endsection
