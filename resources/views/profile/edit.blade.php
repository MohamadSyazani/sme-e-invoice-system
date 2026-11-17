@extends('layouts.app')

@section('title', 'My Profile')

@section('content')
    <h1 class="h3 mb-3">My profile</h1>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold">Account details</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('profile.update') }}" novalidate>
                        @csrf @method('PUT')

                        <div class="mb-3">
                            <label for="name" class="form-label">Full name</label>
                            <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}"
                                   class="form-control @error('name') is-invalid @enderror" required maxlength="255">
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <dl class="row small mb-3">
                            <dt class="col-sm-4 text-muted">Username</dt>
                            <dd class="col-sm-8">{{ $user->username }}</dd>
                            <dt class="col-sm-4 text-muted">Role</dt>
                            <dd class="col-sm-8">{{ $user->role }}</dd>
                            <dt class="col-sm-4 text-muted">Status</dt>
                            <dd class="col-sm-8 mb-0">{{ $user->status }}</dd>
                        </dl>
                        <p class="form-text">Your username, role and status can only be changed by the shop owner.</p>

                        <button type="submit" class="btn btn-primary">Save name</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm h-100 {{ $user->must_change_password ? 'border-warning' : '' }}">
                <div class="card-header fw-semibold">Change password</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('profile.password') }}" novalidate>
                        @csrf @method('PUT')

                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current password</label>
                            <input type="password" id="current_password" name="current_password"
                                   class="form-control @error('current_password') is-invalid @enderror"
                                   required autocomplete="current-password">
                            @error('current_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">New password</label>
                            <input type="password" id="password" name="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   required autocomplete="new-password">
                            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">At least 8 characters, with letters and numbers, and different from your current one.</div>
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">Confirm new password</label>
                            <input type="password" id="password_confirmation" name="password_confirmation"
                                   class="form-control" required autocomplete="new-password">
                        </div>

                        <button type="submit" class="btn btn-primary">Change password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
