{{-- seat-osmm::menu.partials.topbar --}}
@php
  use Illuminate\Support\Facades\Route;

  // Fallback permission checker if none was passed
  $can = $can ?? function($perm){ return empty($perm) || (auth()->check() && auth()->user()->can($perm)); };

  // helper: safe route URL
  $routeUrl = function (?string $named) {
    if (!$named) return '#';
    try {
      return Route::has($named) ? route($named) : '#';
    } catch (\Throwable $e) {
      return '#';
    }
  };

  // helper: label/translation
  $labelOf = function (array $item, string $fallback = '') {
    $k = $item['label'] ?? $item['name'] ?? $fallback;
    try { return __($k); } catch (\Throwable $e) { return $k; }
  };
@endphp

<nav class="navbar navbar-expand navbar-light bg-white border osmm-topbar">
  <ul class="navbar-nav mr-auto flex-nowrap">
    @foreach($menu as $key => $parent)
      @php
        if (!is_array($parent)) continue;
        $showParent   = $can($parent['permission'] ?? null);
        $parentLabel  = $labelOf($parent, $key);
        $parentHref   = $routeUrl($parent['route'] ?? null);
        $hasChildren  = !empty($parent['entries']) && is_array($parent['entries']);
      @endphp

      @if($showParent)
        @if($hasChildren)
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="{{ $parentHref }}" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
              @if(!empty($parent['icon'])) <i class="{{ $parent['icon'] }}"></i> @endif
              <span class="ml-1">{{ $parentLabel }}</span>
            </a>
            <div class="dropdown-menu">
              @foreach($parent['entries'] as $child)
                @php
                  if (!is_array($child)) continue;
                  $showChild  = $can($child['permission'] ?? null);
                  $childHref  = $routeUrl($child['route'] ?? null);
                  $childLabel = $labelOf($child);
                @endphp
                @if($showChild)
                  <a class="dropdown-item" href="{{ $childHref }}">
                    @if(!empty($child['icon'])) <i class="{{ $child['icon'] }}"></i> @endif
                    <span class="ml-1">{{ $childLabel }}</span>
                  </a>
                @endif
              @endforeach
            </div>
          </li>
        @else
          <li class="nav-item">
            <a class="nav-link" href="{{ $parentHref }}">
              @if(!empty($parent['icon'])) <i class="{{ $parent['icon'] }}"></i> @endif
              <span class="ml-1">{{ $parentLabel }}</span>
            </a>
          </li>
        @endif
      @endif
    @endforeach
  </ul>
</nav>

<style>
  .osmm-topbar { overflow-x: auto; white-space: nowrap; }
  .osmm-topbar .navbar-nav > li { margin-right: .5rem; }
  .osmm-topbar .dropdown-menu { max-height: 60vh; overflow: auto; }
</style>
