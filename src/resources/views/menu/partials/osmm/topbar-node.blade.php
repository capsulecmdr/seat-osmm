@php
  $t = fn($v) => __($v ?? '');

  // ----- permission / visibility -----
  $perm   = $item['permission'] ?? null;
  $forced = array_key_exists('visible', $item) ? $item['visible'] : null;

  $canSee = $can ? $can($perm) : true;
  if ($forced === 0 || $forced === '0') $canSee = false;
  if ($forced === 1 || $forced === '1') $canSee = true;

  if (!$canSee) return;

  // ----- label / icon -----
  $label = $t($item['label'] ?? $item['name'] ?? ($key ?? ''));
  $icon  = $item['icon'] ?? null;

  // ----- link (route OR url) -----
  $url   = '#';
  $attrs = '';
  if (!empty($item['route']) && \Illuminate\Support\Facades\Route::has($item['route'])) {
      try { $url = route($item['route']); } catch (\Throwable $e) { $url = '#'; }
  } elseif (!empty($item['url'])) {
      $url = $item['url'];
  }
  if (!empty($item['target'])) {
      $attrs .= ' target="' . e($item['target']) . '" rel="noopener"';
  }

  // ----- children / grandchildren -----
  $children = [];
  if (!empty($item['entries']) && is_array($item['entries'])) {
      foreach ($item['entries'] as $e) if (is_array($e)) $children[] = $e;
  }
  $hasKids = count($children) > 0;
@endphp

@if($hasKids)
  <li class="nav-item dropdown">
    <a href="#" class="nav-link dropdown-toggle"
       data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
      @if($icon)<i class="{{ $icon }}"></i>@endif
      <span class="ml-1">{{ $label }}</span>
    </a>

    <div class="dropdown-menu">
      @foreach($children as $child)
        @php
          // dividers at topbar level
          if (!empty($child['divider'])) {
            echo '<div class="dropdown-divider"></div>';
            continue;
          }

          $cForced = $child['visible'] ?? null;
          $cPerm   = $child['permission'] ?? null;
          $cShow   = $can ? $can($cPerm) : true;
          if ($cForced === 0 || $cForced === '0') $cShow = false;
          if ($cForced === 1 || $cForced === '1') $cShow = true;
          if (!$cShow) continue;

          $cLabel = $t($child['label'] ?? $child['name'] ?? '');
          $cIcon  = $child['icon'] ?? null;

          $cUrl   = '#';
          $cAttrs = '';
          if (!empty($child['route']) && \Illuminate\Support\Facades\Route::has($child['route'])) {
              try { $cUrl = route($child['route']); } catch (\Throwable $e) { $cUrl = '#'; }
          } elseif (!empty($child['url'])) {
              $cUrl = $child['url'];
          }
          if (!empty($child['target'])) {
              $cAttrs .= ' target="' . e($child['target']) . '" rel="noopener"';
          }

          $grand = [];
          if (!empty($child['entries']) && is_array($child['entries'])) {
              foreach ($child['entries'] as $g) if (is_array($g)) $grand[] = $g;
          }
        @endphp

        @if(count($grand))
          {{-- section header, then grandchildren items (no nested dropdowns in BS4) --}}
          <h6 class="dropdown-header">{{ $cLabel }}</h6>

          @foreach($grand as $gc)
            @php
              if (!empty($gc['divider'])) {
                echo '<div class="dropdown-divider"></div>';
                continue;
              }

              $gcForced = $gc['visible'] ?? null;
              $gcPerm   = $gc['permission'] ?? null;
              $gcShow   = $can ? $can($gcPerm) : true;
              if ($gcForced === 0 || $gcForced === '0') $gcShow = false;
              if ($gcForced === 1 || $gcForced === '1') $gcShow = true;
              if (!$gcShow) continue;

              $gcLabel = $t($gc['label'] ?? $gc['name'] ?? '');
              $gcUrl   = '#';
              $gcAttrs = '';
              if (!empty($gc['route']) && \Illuminate\Support\Facades\Route::has($gc['route'])) {
                  try { $gcUrl = route($gc['route']); } catch (\Throwable $e) { $gcUrl = '#'; }
              } elseif (!empty($gc['url'])) {
                  $gcUrl = $gc['url'];
              }
              if (!empty($gc['target'])) {
                  $gcAttrs .= ' target="' . e($gc['target']) . '" rel="noopener"';
              }
            @endphp

            <a class="dropdown-item" href="{{ $gcUrl }}" {!! $gcAttrs !!}>
              {{ $gcLabel }}
            </a>
          @endforeach

          <div class="dropdown-divider"></div>
        @else
          <a class="dropdown-item" href="{{ $cUrl }}" {!! $cAttrs !!}>
            @if($cIcon)<i class="{{ $cIcon }}"></i>@endif
            <span class="ml-1">{{ $cLabel }}</span>
          </a>
        @endif
      @endforeach
    </div>
  </li>
@else
  <li class="nav-item">
    <a class="nav-link" href="{{ $url }}" {!! $attrs !!}>
      @if($icon)<i class="{{ $icon }}"></i>@endif
      <span class="ml-1">{{ $label }}</span>
    </a>
  </li>
@endif
