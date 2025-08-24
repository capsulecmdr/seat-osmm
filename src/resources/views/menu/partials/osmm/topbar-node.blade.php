@php
$hasChildren = !empty($item['entries']) && is_array($item['entries']);
$perm        = $item['permission'] ?? null;
$allowed     = empty($perm) || (is_callable($can) ? $can($perm) : true);
if (!$allowed) { return; }

$iconClass   = trim($item['icon'] ?? 'far fa-circle');
$rawLabel    = $item['label'] ?? ($item['name'] ?? '');
try { $label = __($rawLabel); } catch (\Throwable $e) { $label = $rawLabel; }

$url = '#';
$isNamedRoute = !empty($item['route']) && \Illuminate\Support\Facades\Route::has($item['route']);
if ($isNamedRoute) {
  try { $url = route($item['route']); } catch (\Throwable $e) { $url = '#'; }
}

// classes: root level uses .nav-item, deeper levels use .dropdown-submenu
$level = $level ?? 0;
$isRoot = $level === 0;
@endphp

@if($isRoot)
  <li class="nav-item {{ $hasChildren ? 'dropdown' : '' }}">
    <a class="nav-link {{ $hasChildren ? 'dropdown-toggle' : '' }}" href="{{ $url }}" {{ $hasChildren ? 'data-toggle=dropdown role=button aria-haspopup=true aria-expanded=false' : '' }}>
      <i class="{{ $iconClass }}"></i> <span class="ml-1">{{ $label }}</span>
    </a>

    @if($hasChildren)
      <div class="dropdown-menu">
        @foreach($item['entries'] as $child)
          @if(is_array($child))
            @include('seat-osmm::menu.partials.osmm.topbar-submenu', ['item' => $child, 'can' => $can, 'level' => 1])
          @endif
        @endforeach
      </div>
    @endif
  </li>
@else
  {{-- non-root nodes are handled by topbar-submenu --}}
@endif
