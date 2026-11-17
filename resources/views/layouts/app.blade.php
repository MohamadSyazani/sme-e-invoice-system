<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') | SME E-Invoice System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            body { background: #fff !important; }
            .card { border: 0 !important; box-shadow: none !important; }
        }
    </style>
    @stack('styles')
</head>
<body class="bg-light">

@auth
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 d-print-none">
        <div class="container">
            <a class="navbar-brand fw-semibold" href="{{ route('dashboard') }}">SME E-Invoice</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                    aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                           href="{{ route('dashboard') }}">Dashboard</a>
                    </li>

                    {{-- Module 1 — Owner only --}}
                    @if (auth()->user()->isOwner())
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('staff.*') ? 'active' : '' }}"
                               href="{{ route('staff.index') }}">Staff</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}"
                               href="{{ route('reports.login-logs') }}">Login Report</a>
                        </li>
                    @endif

                    {{-- Other modules: add your nav links here (Products, Customers, POS, Ledger...). --}}
                    @stack('nav-links')
                </ul>

                <div class="d-flex align-items-center gap-3 text-white">
                    <a href="{{ route('profile.edit') }}" class="small text-white text-decoration-none"
                       title="My profile">
                        {{ auth()->user()->name }}
                        <span class="badge {{ auth()->user()->isOwner() ? 'text-bg-warning' : 'text-bg-info' }}">
                            {{ auth()->user()->role }}
                        </span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-light btn-sm">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>
@endauth

<main class="container pb-5">
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show d-print-none" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('warning'))
        <div class="alert alert-warning alert-dismissible fade show d-print-none" role="alert">
            {{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show d-print-none" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @yield('content')
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
