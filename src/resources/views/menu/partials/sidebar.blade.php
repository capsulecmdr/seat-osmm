@php
  use Illuminate\Support\Facades\Route as RouteFacade;

  // Permission checker (fallback if not provided)
  $can = $can ?? function ($perm) {
      return empty($perm) || (auth()->check() && auth()->user()->can($perm));
  };

  // Safe route resolver: returns URL or '#'
  $resolveUrl = function (?string $routeName) {
      if (!$routeName) return '#';
      return RouteFacade::has($routeName) ? route($routeName) : '#';
  };

  // Small helper to render a text label
  $labelOf = function (array $item, $fallback) {
      // If your labels are translation keys (e.g. "web::seat.home"), __() will translate them
      return __($item['label'] ?? $item['name'] ?? $fallback);
  };

  // base classes to mimic a sidebar look; tweak to match your theme/AdminLTE
  $navClass   = 'nav flex-column nav-pills';
  $itemClass  = 'nav-item';
  $linkClass  = 'nav-link d-flex align-items-center';
  $iconClass  = 'mr-2';
@endphp

<nav class="osmm-sidebar">
  <ul class="{{ $navClass }}">
    @foreach($menu as $topKey => $section)
      @php
        if (!is_array($section)) continue;
        $showParent = $can($section['permission'] ?? null);
        $hasKids = !empty($section['entries']) && is_array($section['entries']);
        $parentUrl = $resolveUrl($section['route'] ?? null);
        $parentLabel = $labelOf($section, $topKey);
      @endphp

      @if($showParent)
        <li class="{{ $itemClass }}">
          <a href="{{ $hasKids ? '#' : $parentUrl }}" class="{{ $linkClass }}">
            @if(!empty($section['icon']))
              <i class="{{ $section['icon'] }} {{ $iconClass }}"></i>
            @endif
            <span>{{ $parentLabel }}</span>
            @if($hasKids)
              <span class="ml-auto"><i class="fas fa-angle-down small text-muted"></i></span>
            @endif
          </a>

          @if($hasKids)
            <ul class="nav flex-column ml-3 my-1">
              @foreach($section['entries'] as $child)
                @if(is_array($child))
                  @php
                    $showChild = $can($child['permission'] ?? null);
                    $childUrl  = $resolveUrl($child['route'] ?? null);
                    $childLabel = $labelOf($child, $child['name'] ?? '—');
                  @endphp

                  @if($showChild)
                    <li class="{{ $itemClass }}">
                      <a href="{{ $childUrl }}" class="{{ $linkClass }}">
                        @if(!empty($child['icon']))
                          <i class="{{ $child['icon'] }} {{ $iconClass }}"></i>
                        @else
                          <i class="fas fa-circle {{ $iconClass }}"></i>
                        @endif
                        <span>{{ $childLabel }}</span>
                      </a>
                    </li>
                  @endif
                @endif
              @endforeach
            </ul>
          @endif
        </li>
      @endif
    @endforeach
  </ul>
</nav>

{{-- Optional minimal styling for a “sidebar” feel; tweak or move to your CSS --}}
<style>
  .osmm-sidebar .nav-link { border-radius: 0; }
  .osmm-sidebar .nav-link:hover { background: rgba(0,0,0,.04); }
  .osmm-sidebar .nav .nav { border-left: 2px solid rgba(0,0,0,.08); }
</style>
