@extends('web::layouts.app')
@section('page_title', 'OSMM Menu Manager')

@section('content')
<div class="row">
  {{-- Left: Native sidebar --}}
  <div class="col-md-4">
    <div class="card mb-3">
      <div class="card-header"><strong>Native Sidebar</strong> <small class="text-muted">package.sidebar</small></div>
      <div class="card-body p-0" style="max-height:60vh; overflow:auto">
        @include('seat-osmm::menu.partials.sidebar', [
          'menu' => $native,
          'source' => 'native',
          'can' => $can ?? null
        ])
      </div>
    </div>

    <div class="card">
      <div class="card-header"><strong>Details: Native</strong></div>
      <div id="details-native" class="card-body small text-monospace">
        <em>Select an item above…</em>
      </div>
    </div>
  </div>

  {{-- Center: DB Overrides list --}}
  <div class="col-md-4">
    <div class="card mb-3">
      <div class="card-header"><strong>Overrides in Database</strong> <small class="text-muted">osmm_menu_items</small></div>
      <div class="card-body p-0" style="max-height:60vh; overflow:auto">
        @include('seat-osmm::menu.partials.overrides_list', ['rows' => $dbRows])
      </div>
    </div>

    <div class="card">
      <div class="card-header"><strong>Details: Override</strong></div>
      <div id="details-overrides" class="card-body small text-monospace">
        <em>Select an override row…</em>
      </div>
    </div>
  </div>

  {{-- Right: Merged sidebar --}}
  <div class="col-md-4">
    <div class="card mb-3">
      <div class="card-header"><strong>Merged Sidebar</strong> <small class="text-muted">DB overrides applied</small></div>
      <div class="card-body p-0" style="max-height:60vh; overflow:auto">
        @include('seat-osmm::menu.partials.sidebar', [
          'menu' => $merged,
          'source' => 'merged',
          'can' => $can ?? null
        ])
      </div>
    </div>

    <div class="card">
      <div class="card-header"><strong>Details: Merged</strong></div>
      <div id="details-merged" class="card-body small text-monospace">
        <em>Select an item above…</em>
      </div>
    </div>
  </div>
</div>

{{-- Simple JS to render details for any clicked item --}}
@push('javascript')
<script>
(function(){
  function renderDetails(targetId, data){
    const el = document.getElementById(targetId);
    if(!el) return;
    const pretty = JSON.stringify(data, null, 2);
    el.textContent = pretty;
  }

  function wireClicks(selector, targetId){
    document.addEventListener('click', function(ev){
      const node = ev.target.closest(selector);
      if(!node) return;
      ev.preventDefault();
      // highlight selection
      document.querySelectorAll(selector+'.active').forEach(n => n.classList.remove('active'));
      node.classList.add('active');
      try {
        const payload = JSON.parse(node.dataset.item || '{}');
        renderDetails(targetId, payload);
      } catch(e) { /* ignore */ }
    });
  }

  // Sidebars (left/right)
  wireClicks('[data-osmm-item="native"]',  'details-native');
  wireClicks('[data-osmm-item="merged"]',  'details-merged');

  // Center overrides
  wireClicks('[data-osmm-override-row]',   'details-overrides');
})();
</script>
@endpush

{{-- A pinch of styling --}}
<style>
  .osmm-sidebar a.osmm-link { cursor: pointer; }
  .osmm-sidebar .osmm-link.active,
  .osmm-overrides .list-group-item.active {
    background: rgba(0,123,255,.1);
    border-left: 3px solid #007bff;
  }
  pre { margin: 0; }
</style>

{{-- Quick-create forms (minimal; expand as needed) --}}
<div class="card mt-3">
  <div class="card-header">Create / Update Overrides</div>
  <div class="card-body">
    <form method="post" action="{{ route('osmm.menu.parent.upsert') }}" class="mb-3">
      @csrf
      <h6 class="text-muted">Parent</h6>
      <div class="form-row">
        <div class="col"><input name="name" class="form-control" placeholder="Name"></div>
        <div class="col"><input name="icon" class="form-control" placeholder="Icon (e.g., fas fa-home)"></div>
        <div class="col"><input name="route_segment" class="form-control" placeholder="route_segment"></div>
      </div>
      <div class="form-row mt-2">
        <div class="col"><input name="route" class="form-control" placeholder="route (optional)"></div>
        <div class="col"><input name="permission" class="form-control" placeholder="permission (optional)"></div>
        <div class="col"><input name="order" type="number" class="form-control" placeholder="order"></div>
      </div>
      <button class="btn btn-primary btn-sm mt-2">Save Parent</button>
    </form>

    <form method="post" action="{{ route('osmm.menu.child.upsert') }}" class="mb-3">
      @csrf
      <h6 class="text-muted">Child</h6>
      <div class="form-row">
        <div class="col">
          <select name="parent_id" class="form-control">
            <option value="">Select Parent</option>
            @foreach($parentOptions as $p)
              <option value="{{ DB::table('osmm_menu_items')->where('route_segment',$p['seg'])->whereNull('parent')->value('id') }}">
                {{ $p['label'] }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="col"><input name="name" class="form-control" placeholder="Name"></div>
        <div class="col"><input name="icon" class="form-control" placeholder="Icon"></div>
      </div>
      <div class="form-row mt-2">
        <div class="col"><input name="route" class="form-control" placeholder="route"></div>
        <div class="col"><input name="permission" class="form-control" placeholder="permission"></div>
        <div class="col"><input name="order" type="number" class="form-control" placeholder="order"></div>
      </div>
      <button class="btn btn-primary btn-sm mt-2">Save Child</button>
    </form>

    <form method="post" action="{{ route('osmm.menu.reset') }}" onsubmit="return confirm('Clear ALL overrides?')">
      @csrf
      <button class="btn btn-danger btn-sm">Reset All Overrides</button>
    </form>
  </div>
</div>
@endsection
