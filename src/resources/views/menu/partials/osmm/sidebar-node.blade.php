@php
$hasChildren = !empty($item['entries']) && is_array($item['entries']);
$perm        = $item['permission'] ?? null;
$allowed     = empty($perm) || (is_callable($can) ? $can($perm) : true);
if (!$allowed) { return; } // hide if not allowed

$iconClass   = trim($item['icon'] ?? 'far fa-circle');
$rawLabel    = $item['label'] ?? ($item['name'] ?? '');
try { $label = __($rawLabel); } catch (\Throwable $e) { $label = $rawLabel; }

$url = '#';
$isNamedRoute = !empty($item['route']) && \Illuminate\Support\Facades\Route::has($item['route']);
if ($isNamedRoute) {
  try { $url = route($item['route']); } catch (\Throwable $e) { $url = '#'; }
}

$checkActive = function($node) use (&$checkActive) {
    // active if current route matches named route
    if (!empty($node['route']) && \Illuminate\Support\Facades\Route::has($node['route'])) {
        if (request()->routeIs($node['route'])) return true;
    }
    // or any child active
    if (!empty($node['entries']) && is_array($node['entries'])) {
        foreach ($node['entries'] as $c) {
            if (is_array($c) && $checkActive($c)) return true;
        }
    }
    return false;
};
$isActive = $checkActive($item);

// AdminLTE-ish classes; tweak to your theme if needed
$liClass   = 'nav-item'.($hasChildren ? ' has-treeview' : '');
$linkClass = 'nav-link'.($isActive ? ' active' : '');
$ulChild   = 'nav nav-treeview';
@endphp

<li class="{{ $liClass }} {{ $isActive && $hasChildren ? 'menu-open' : '' }}">
  <a href="{{ $url }}" class="{{ $linkClass }}">
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
      @foreach($item['entries'] as $child)
        @if(is_array($child))
          @include('seat-osmm::menu.partials.sidebar-node', ['item' => $child, 'can' => $can, 'level' => ($level ?? 0) + 1])
        @endif
      @endforeach
    </ul>
  @endif
</li>
