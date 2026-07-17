<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') · {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .admin-sidebar { width: 220px; min-height: 100vh; }
        .admin-sidebar .nav-link { color: rgba(255,255,255,.75); }
        .admin-sidebar .nav-link.active,
        .admin-sidebar .nav-link:hover { color: #fff; background: rgba(255,255,255,.1); }
    </style>
</head>
<body class="bg-light">

    <div class="d-flex">
        {{-- Sidebar --}}
        <nav class="admin-sidebar bg-dark p-3 flex-shrink-0">
            <div class="fs-5 fw-bold text-white mb-4">{{ config('app.name') }}</div>
            <ul class="nav nav-pills flex-column gap-1">
                @foreach (config('admin_menu') as $item)
                    @if (is_null($item['permission']) || auth()->user()->hasPermission($item['permission']))
                        <li class="nav-item">
                            <a href="{{ route($item['route']) }}"
                               class="nav-link {{ request()->routeIs($item['route'].'*') || request()->routeIs($item['route']) ? 'active' : '' }}">
                                <i class="bi {{ $item['icon'] }} me-2"></i>{{ $item['label'] }}
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>

        {{-- Main content --}}
        <div class="flex-grow-1">
            <nav class="navbar navbar-light bg-white border-bottom px-3">
                <span class="navbar-text">@yield('title', 'Admin')</span>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted small">{{ auth()->user()->name }} ({{ auth()->user()->role?->name }})</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary btn-sm">Log out</button>
                    </form>
                </div>
            </nav>

            <div class="container-fluid py-4">
                @if (session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
