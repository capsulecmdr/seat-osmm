@php
  // ----- permission / visibility -----
  $perm    = $item['permission'] ?? null;
  $forced  = array_key_exists('visible', $item) ? $item['visible'] : null;

  // default permission-based visibility
  $allowed = empty($perm) || (is_callable($can) ? $can($perm) : true);
  // apply forced visibility if present
  if ($forced === 0 || $forced === '0') $allowed = false;
  if ($forced === 1 || $forced === '1') $allowed = true;

  if (!$allowed) { return; }

  // ----- label / icon -----
  $rawLabel  = $item['label'] ?? ($item['name'] ?? '');
  try { $label = __($rawLabel); } catch (\Throwable $e) { $label = $rawLabel; }
  $iconClass = trim($item['icon'] ?? 'far fa-circle');

  // ----- link (route OR url) -----
  $href  = '#';
  $attrs = '';
  if (!empty($item['route']) && \Illuminate\Support\Facades\Route::has($item['route'])) {
      try { $href = route($item['route']); } catch (\Throwable $e) { $href = '#'; }
  } elseif (!empty($item['url'])) {
      $href = $item['url'];
  }
  if (!empty($item['target'])) {
      $attrs .= ' target="' . e($item['target']) . '" rel="noopener"';
  }

  // ----- children -----
  $children = [];
  if (!empty($item['entries']) && is_array($item['entries'])) {
      foreach ($item['entries'] as $e) if (is_array($e)) $children[] = $e;
  }
  $hasChildren = count($children) > 0;

  // ----- active state (route-aware; external can't be reliably matched) -----
  $checkActive = function($node) use (&$checkActive) {
      if (!empty($node['route']) && \Illuminate\Support\Facades\Route::has($node['route'])) {
          if (request()->routeIs($node['route'])) return true;
      }
      if (!empty($node['entries']) && is_array($node['entries'])) {
          foreach ($node['entries'] as $c) {
              if (is_array($c) && $checkActive($c)) return true;
          }
      }
      return false;
  };
  $isActive = $checkActive($item);

  // ----- classes (AdminLTE-like) -----
  $liClass   = 'nav-item' . ($hasChildren ? ' has-treeview' : '');
  $linkClass = 'nav-link' . ($isActive ? ' active' : '');
  $ulChild   = 'nav nav-treeview';
@endphp

<li class="{{ $liClass }} {{ $isActive && $hasChildren ? 'menu-open' : '' }}">
  <a href="{{ $href }}" class="{{ $linkClass }}" {!! $attrs !!}>
    <i class="nav-icon {{ $iconClass }}"></i>
    <p>
      {{ $label }}
      @if($hasChildren)
        <i class="right fas fa-angle-left"></i>
      @endif
    </p>
  </a>

  @if($hasChildren)
    <ul class="{{ $ulChild }}">
      @foreach($children as $child)
        @if(!empty($child['divider']))
          {{-- divider row between groups (custom links vs native, etc.) --}}
          <li class="nav-item w-100 my-2"><hr class="m-0"></li>
          @continue
        @endif

        @include('seat-osmm::menu.partials.osmm.sidebar-node', [
          'item'  => $child,
          'can'   => $can,
          'level' => ($level ?? 0) + 1
        ])
      @endforeach
    </ul>
  @endif
</li>
