<ul class="navbar-nav pt-lg-3">
    @can('dashboard.view')
    <li class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif>
            <span class="nav-link-icon"><x-icon name="dashboard" /></span>
            <span class="nav-link-title">Dashboard</span>
        </a>
    </li>
    @endcan
    @foreach(\App\Support\PermissionCatalog::navigation() as $module => $items)
        @continue($module === 'dashboard')
        @php
            $visibleItems = collect($items)->filter(fn ($item) => ($item['route'] && ! ($item['any_permission'] ?? false))
                ? auth()->user()->can($item['permissions'][0])
                : auth()->user()->canAny($item['permissions']));
            $isCurrent = fn ($item) => $item['route'] && (request()->routeIs($item['route'])
                || ($item['route'] === 'catalog.espacios.index' && request()->routeIs('catalog.espacios.map*')));
            $expanded = $visibleItems->contains($isCurrent);
            $icon = ['gestion' => 'parking', 'operaciones' => 'arrows', 'reportes' => 'report', 'administracion' => 'users'][$module];
        @endphp
        @continue($visibleItems->isEmpty())
        <li class="nav-item">
            <button type="button" class="nav-link w-100 border-0 bg-transparent text-start" data-bs-toggle="collapse" data-bs-target="#nav-{{ $module }}" aria-controls="nav-{{ $module }}" aria-expanded="{{ $expanded ? 'true' : 'false' }}">
                <span class="nav-link-icon"><x-icon :name="$icon" /></span>
                <span class="nav-link-title">{{ config('permissions.modules.'.$module) }}</span>
                <span class="ms-auto" aria-hidden="true">⌄</span>
            </button>
            <ul class="navbar-nav collapse {{ $expanded ? 'show' : '' }} ps-3" id="nav-{{ $module }}">
                @foreach($visibleItems as $item)
                    <li class="nav-item {{ $isCurrent($item) ? 'active' : '' }}">
                        @if($item['pending'])
                            <span class="nav-link module-pending" aria-disabled="true"><span class="nav-link-title">{{ $item['label'] }}</span><span class="module-pending-label">Próximo</span></span>
                        @else
                            <a class="nav-link" href="{{ route($item['route']) }}" @if($isCurrent($item)) aria-current="page" @endif><span class="nav-link-title">{{ $item['label'] }}</span></a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </li>
    @endforeach
</ul>
