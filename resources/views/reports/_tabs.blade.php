<ul class="nav nav-tabs mb-3 d-print-none">
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('reports.login-logs*') ? 'active' : '' }}"
           href="{{ route('reports.login-logs') }}">Successful logins</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('reports.failed-logins') ? 'active' : '' }}"
           href="{{ route('reports.failed-logins') }}">Failed attempts</a>
    </li>
</ul>
