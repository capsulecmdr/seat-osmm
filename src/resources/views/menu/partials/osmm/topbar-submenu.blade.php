@php
$hasChildren = !empty($item['entries']) && is_array($item['entries']);
$perm        = $item['permission'] ?? null;
$allowed     = empty($perm) || (is_callable($can) ? $can($perm) : true);
if (!$allowed) { return; }

$iconClass   = trim($item['icon'] ?? '');
$rawLabel    = $item['label'] ?? ($item['name'] ?? '');
try { $label = __($rawLabel); } catch (\Throwable $e) { $label = $rawLabel; }

$url = '#';
$isNamedRoute = !empty($item['route']) && \Illuminate\Support\Facades\Route::has($item['route']);
if ($isNamedRoute) {
  try { $url = route($item['route']); } catch (\Throwable $e) { $url = '#'; }
}

$level = $level ?? 1;
@endphp

@if($hasChildren)
  <div class="dropdown-submenu">
    <a class="dropdown-item dropdown-toggle" href="{{ $url }}">
      @if($iconClass)<i class="{{ $iconClass }}"></i> @endif
      {{ $label }}
    </a>
    <div class="dropdown-menu">
      @foreach($item['entries'] as $child)
        @if(is_array($child))
          @include('seat-osmm::partials.osmm.topbar-submenu', ['item' => $child, 'can' => $can, 'level' => $level + 1])
        @endif
      @endforeach
    </div>
  </div>
@else
  <a class="dropdown-item" href="{{ $url }}">
    @if($iconClass)<i class="{{ $iconClass }}"></i> @endif
    {{ $label }}
  </a>
@endif
