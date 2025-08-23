@php
  use Illuminate\Support\Facades\Route as RouteFacade;

  $can = $can ?? function ($perm) {
      return empty($perm) || (auth()->check() && auth()->user()->can($perm));
  };

  $resolveUrl = function (?string $routeName) {
      if (!$routeName) return '#';
      return RouteFacade::has($routeName) ? route($routeName) : '#';
  };

  $labelOf = function (array $item, $fallback) {
      return __($item['label'] ?? $item['name'] ?? $fallback);
  };

  $navClass  = 'nav flex-column nav-pills osmm-sidebar';
  $itemClass = 'nav-item';
  $linkClass = 'nav-link d-flex align-items-center osmm-link';
  $iconClass = 'mr-2';
@endphp

<nav class="osmm-sidebar">
  <ul class="{{ $navClass }}">
    @foreach($menu as $topKey => $section)
      @php
        if (!is_array($section)) continue;
        $showParent = $can($section['permission'] ?? null);
        $hasKids    = !empty($section['entries']) && is_array($section['entries']);
        $parentUrl  = $resolveUrl($section['route'] ?? null);
        $parentLbl  = $labelOf($section, $topKey);
        $payloadParent = [
          'type'          => 'parent',
          'source'        => $source ?? 'unknown',
          'key'           => $topKey,
          'name'          => $section['name'] ?? null,
          'label'         => $section['label'] ?? null,
          'icon'          => $section['icon'] ?? null,
          'route_segment' => $section['route_segment'] ?? null,
          'route'         => $section['route'] ?? null,
          'url'           => $parentUrl,
          'permission'    => $section['permission'] ?? null,
          'plural'        => $section['plural'] ?? null,
        ];
      @endphp

      @if($showParent)
        <li class="{{ $itemClass }}">
          <a href="{{ $hasKids ? '#' : $parentUrl }}"
             class="{{ $linkClass }}"
             data-osmm-item="{{ $source ?? 'unknown' }}"
             data-item='@json($payloadParent)'>
            @if(!empty($section['icon']))
              <i class="{{ $section['icon'] }} {{ $iconClass }}"></i>
            @endif
            <span>{{ $parentLbl }}</span>
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
                    $childLbl  = $labelOf($child, $child['name'] ?? '—');
                    $childKey  = $child['route'] ?? ($child['name'] ?? null);
                    $payloadChild = [
                      'type'          => 'child',
                      'source'        => $source ?? 'unknown',
                      'parent_key'    => $topKey,
                      'key'           => $childKey,
                      'name'          => $child['name'] ?? null,
                      'label'         => $child['label'] ?? null,
                      'icon'          => $child['icon'] ?? null,
                      'route'         => $child['route'] ?? null,
                      'url'           => $childUrl,
                      'permission'    => $child['permission'] ?? null,
                      'plural'        => $child['plural'] ?? null,
                    ];
                  @endphp
                  @if($showChild)
                    <li class="{{ $itemClass }}">
                      <a href="{{ $childUrl }}"
                         class="{{ $linkClass }}"
                         data-osmm-item="{{ $source ?? 'unknown' }}"
                         data-item='@json($payloadChild)'>
                        @if(!empty($child['icon']))
                          <i class="{{ $child['icon'] }} {{ $iconClass }}"></i>
                        @else
                          <i class="fas fa-circle {{ $iconClass }}"></i>
                        @endif
                        <span>{{ $childLbl }}</span>
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
