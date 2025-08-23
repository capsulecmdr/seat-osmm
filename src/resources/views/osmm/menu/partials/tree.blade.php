<ul class="list-unstyled">
@foreach($menu as $key => $item)
  <li class="mb-2">
    <div>
      <strong>{{ $item['name'] ?? $key }}</strong>
      @if(!empty($item['icon'])) <i class="{{ $item['icon'] }}"></i> @endif
      @if(!empty($item['route_segment'])) <small class="text-muted">[{{ $item['route_segment'] }}]</small> @endif
      @if(!empty($item['route'])) <small class="badge badge-light">{{ $item['route'] }}</small> @endif
      @if(!empty($item['permission'])) <small class="badge badge-info">{{ $item['permission'] }}</small> @endif
    </div>

    @if(!empty($item['entries']) && is_array($item['entries']))
      <ul class="ml-3 mt-1">
        @foreach($item['entries'] as $child)
          @if(is_array($child))
            <li>
              {{ $child['name'] ?? '' }}
              @if(!empty($child['icon'])) <i class="{{ $child['icon'] }}"></i> @endif
              @if(!empty($child['route'])) <small class="badge badge-light">{{ $child['route'] }}</small> @endif
              @if(!empty($child['permission'])) <small class="badge badge-info">{{ $child['permission'] }}</small> @endif
            </li>
          @endif
        @endforeach
      </ul>
    @endif
  </li>
@endforeach
</ul>
