<nav class="nav nav-tabs mb-3" aria-label="Vistas de espacios">
    <a class="nav-link {{ request()->routeIs('catalog.espacios.index') ? 'active' : '' }}" href="{{ route('catalog.espacios.index') }}" @if(request()->routeIs('catalog.espacios.index')) aria-current="page" @endif>Listado</a>
    <a class="nav-link {{ request()->routeIs('catalog.espacios.map') ? 'active' : '' }}" href="{{ route('catalog.espacios.map') }}" @if(request()->routeIs('catalog.espacios.map')) aria-current="page" @endif>Mapa de ocupación</a>
</nav>
