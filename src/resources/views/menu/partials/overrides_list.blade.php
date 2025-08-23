@php
  // build a quick parent name map for display
  $parentNames = collect($rows)->whereNull('parent')->pluck('name','id');
@endphp

<div class="osmm-overrides list-group list-group-flush">
  @forelse($rows as $row)
    @php
      $isParent = is_null($row->parent);
      $display  = $isParent
        ? "[PARENT] {$row->name}  " . ($row->route_segment ? " [{$row->route_segment}]" : '')
        : "↳ {$row->name}";

      $payload = [
        'db' => true,
        'type' => $isParent ? 'parent' : 'child',
        'id' => $row->id,
        'parent_id' => $row->parent,
        'parent_name' => $row->parent ? ($parentNames[$row->parent] ?? null) : null,
        'order' => $row->order,
        'name' => $row->name,
        'icon' => $row->icon,
        'route_segment' => $row->route_segment,
        'route' => $row->route,
        'permission' => $row->permission,
        'created_at' => (string)$row->created_at,
        'updated_at' => (string)$row->updated_at,
      ];
    @endphp
    <a href="#"
       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
       data-osmm-override-row
       data-item='@json($payload)'>
      <span class="text-truncate">
        @if(!$isParent) <span class="text-muted mr-1">({{ $row->parent }})</span> @endif
        {!! $isParent ? '<strong>'.$display.'</strong>' : e($display) !!}
      </span>
      <small class="text-muted">#{{ $row->id }}</small>
    </a>
  @empty
    <div class="p-3 text-muted">No overrides found.</div>
  @endforelse
</div>
