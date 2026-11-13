<!doctype html>
<html lang="es" data-bs-navbar-position="combined">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · Estacionamiento</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="parking-admin">
<div class="page">
    <aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark" aria-label="Menú administrativo">
        <div class="container-fluid">
            <a class="navbar-brand gap-2" href="{{ route(auth()->user()->startRoute()) }}">
                <span class="brand-mark"><x-icon name="parking" /></span>
                <span class="brand-copy"><span class="brand-name">Estacionamiento</span><span class="brand-caption">Gestión y control</span></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu" aria-controls="sidebar-menu" aria-expanded="false" aria-label="Abrir menú">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="sidebar-menu">
                <div id="permission-navigation" class="w-100">@include('layouts.partials.navigation')</div>
                <div class="sidebar-note m-3 p-3 rounded">
                    <div class="fw-semibold mb-1">Gestión del estacionamiento</div>
                    <small>Control de estancias, caja y reportes del estacionamiento.</small>
                </div>
            </div>
        </div>
    </aside>
    <header class="navbar navbar-expand-md d-print-none">
        <div class="container-fluid px-3 px-md-4">
            <span class="navbar-brand mb-0">Panel de control</span>
            <div class="d-flex align-items-center gap-3 ms-auto">
                <div class="text-end">
                    <div class="fw-medium">{{ auth()->user()->name }}</div>
                    <small class="text-secondary">{{ auth()->user()->role_label }}</small>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-outline-secondary" type="submit">Salir</button>
                </form>
            </div>
        </div>
    </header>
    <div class="page-wrapper">
        <div class="page-header">
            <div class="container-fluid px-3 px-md-4">
                <div class="page-pretitle">Gestión operativa</div>
                <h1 class="page-title">@yield('title', 'Dashboard')</h1>
                <p class="text-secondary mt-2 mb-0">@yield('description', 'Administración y control de acceso al sistema.')</p>
            </div>
        </div>
        <main class="page-body">
            <div class="container-fluid px-3 px-md-4">
                @if(session('status'))
                    <div class="alert alert-success" role="status">{{ session('status') }}</div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
                @endif
                @yield('content')
            </div>
        </main>
        <footer class="footer footer-transparent py-3">
            <div class="container-fluid px-3 px-md-4 text-secondary small">Sistema de Estacionamiento · Gestión y control · {{ auth()->user()->role_label }}</div>
        </footer>
    </div>
</div>
</body>
</html>
