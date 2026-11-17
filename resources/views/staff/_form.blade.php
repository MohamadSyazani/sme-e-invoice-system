@php
    /** @var \App\Models\User|null $staff */
    $staff = $staff ?? null;
    $editingSelf = $staff && $staff->is(auth()->user());
    $currentRole = old('role', $staff?->role ?? \App\Models\User::ROLE_CASHIER);
    $currentStatus = old('status', $staff?->status ?? \App\Models\User::STATUS_ACTIVE);
@endphp

<form method="POST" action="{{ $action }}" novalidate>
    @csrf
    @if ($staff)
        @method('PUT')
    @endif

    <div class="row g-3">
        <div class="col-md-6">
            <label for="name" class="form-label">Full name</label>
            <input type="text" id="name" name="name" value="{{ old('name', $staff?->name) }}"
                   class="form-control @error('name') is-invalid @enderror" required maxlength="255">
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-6">
            <label for="username" class="form-label">Username (used to log in)</label>
            <input type="text" id="username" name="username" value="{{ old('username', $staff?->username) }}"
                   class="form-control @error('username') is-invalid @enderror" required maxlength="50" autocomplete="off">
            @error('username') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-6">
            <label for="role" class="form-label">Role</label>
            @if ($editingSelf)
                {{-- Disabled fields are not submitted, so send the unchanged values as hidden inputs. --}}
                <input type="hidden" name="role" value="{{ $staff->role }}">
                <select id="role" class="form-select" disabled>
                    <option>{{ $staff->role }}</option>
                </select>
            @else
                <select id="role" name="role" class="form-select @error('role') is-invalid @enderror" required>
                    @foreach (\App\Models\User::ROLES as $role)
                        <option value="{{ $role }}" @selected($currentRole === $role)>{{ ucfirst(strtolower($role)) }}</option>
                    @endforeach
                </select>
            @endif
            @error('role') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            <div class="form-text">Owner sees everything. Cashier only gets POS, the customer list and retail prices.</div>
        </div>

        <div class="col-md-6">
            <label for="status" class="form-label">Status</label>
            @if ($editingSelf)
                <input type="hidden" name="status" value="{{ $staff->status }}">
                <select id="status" class="form-select" disabled>
                    <option>{{ $staff->status }}</option>
                </select>
            @else
                <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                    @foreach (\App\Models\User::STATUSES as $status)
                        <option value="{{ $status }}" @selected($currentStatus === $status)>{{ ucfirst(strtolower($status)) }}</option>
                    @endforeach
                </select>
            @endif
            @error('status') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            <div class="form-text">Inactive staff cannot log in.</div>
        </div>

        @if ($editingSelf)
            <div class="col-12">
                <div class="alert alert-info py-2 mb-0">
                    You are editing your own account, so your role and status are locked.
                </div>
            </div>
        @endif

        <div class="col-md-6">
            <label for="password" class="form-label">
                Password @if ($staff) <span class="text-muted">(leave blank to keep current)</span> @endif
            </label>
            <input type="password" id="password" name="password"
                   class="form-control @error('password') is-invalid @enderror"
                   autocomplete="new-password" @required(! $staff)>
            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <div class="form-text">At least 8 characters, with letters and numbers.</div>
        </div>

        <div class="col-md-6">
            <label for="password_confirmation" class="form-label">Confirm password</label>
            <input type="password" id="password_confirmation" name="password_confirmation"
                   class="form-control" autocomplete="new-password" @required(! $staff)>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary">{{ $staff ? 'Save changes' : 'Create account' }}</button>
        <a href="{{ route('staff.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
