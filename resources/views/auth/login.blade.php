@extends('layouts.app')

@section('title', 'Login')

@section('content')
    <div class="row justify-content-center align-items-center" style="min-height: 80vh;">
        <div class="col-md-6 col-lg-4">
            <div class="text-center mb-4">
                <h1 class="h3 fw-bold mb-1">SME E-Invoice System</h1>
                <p class="text-muted mb-0">Hardware shop POS, inventory &amp; e-invoicing</p>
            </div>

            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">Sign in</h2>

                    <form method="POST" action="{{ route('login.store') }}" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" id="username" name="username" value="{{ old('username') }}"
                                   class="form-control @error('username') is-invalid @enderror"
                                   required autofocus autocomplete="username">
                            @error('username')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" id="password" name="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   required autocomplete="current-password">
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
                            <label class="form-check-label" for="remember">Keep me signed in</label>
                        </div>

                        @if ($retryAfter > 0)
                            <div id="lockout-alert" class="alert alert-warning py-2 mb-3" role="alert"
                                 data-seconds="{{ $retryAfter }}" data-username="{{ $lockedUsername }}">
                                Too many attempts, please wait
                                <strong id="lockout-timer">{{ gmdate('i:s', $retryAfter) }}</strong>
                            </div>
                        @endif

                        <button type="submit" id="login-button" class="btn btn-primary w-100"
                                @disabled($retryAfter > 0)>Login</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@if ($retryAfter > 0)
    @push('scripts')
        <script>
            (function () {
                var alertBox = document.getElementById('lockout-alert');
                var timer = document.getElementById('lockout-timer');
                var button = document.getElementById('login-button');
                var usernameInput = document.getElementById('username');
                var lockedUsername = alertBox.dataset.username;
                var endsAt = Date.now() + parseInt(alertBox.dataset.seconds, 10) * 1000;
                var expired = false;
                var handle;

                function pad(n) { return String(n).padStart(2, '0'); }

                // The lock belongs to one username only: typing a different username frees the form.
                function render() {
                    var same = usernameInput.value.trim().toLowerCase() === lockedUsername;
                    alertBox.className = 'alert py-2 mb-3 ' + (expired ? 'alert-success' : 'alert-warning');
                    alertBox.hidden = !same;
                    button.disabled = same && !expired;
                }

                function tick() {
                    var left = Math.max(0, Math.ceil((endsAt - Date.now()) / 1000));
                    timer.textContent = pad(Math.floor(left / 60)) + ':' + pad(left % 60);

                    if (left === 0) {
                        clearInterval(handle);
                        expired = true;
                        alertBox.textContent = 'You can try again now.';
                        render();
                    }
                }

                usernameInput.addEventListener('input', render);
                handle = setInterval(tick, 250);
                tick();
                render();
            })();
        </script>
    @endpush
@endif
