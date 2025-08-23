@extends('web::layouts.app')
@section('page_title', 'OSMM Menu Manager')

@php
  $parentOptions = $parentOptions ?? collect($native)->map(function ($v, $k) {
      $seg = $v['route_segment'] ?? $k;
      $parentId = \DB::table('osmm_menu_items')
          ->whereNull('parent')->where('route_segment', $seg)->value('id');
      return [
          'key'       => $k,
          'name'      => $v['name'] ?? $k,
          'seg'       => $seg,
          'label'     => ($v['name'] ?? $k).' ['.$seg.']',
          'parent_id' => $parentId,
      ];
  })->values()->all();
@endphp

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
      <div id="details-native" class="card-body small ">
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
      <div id="details-overrides" class="card-body small">
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
      <div id="details-merged" class="card-body small">
        <em>Select an item above…</em>
      </div>
    </div>
  </div>
</div>

{{-- Simple JS to render details for any clicked item --}}
@push('javascript')
<script>
(function(){
  // ---- helpers ---------------------------------------------------
  const esc = (s) => (s === null || s === undefined) ? '' :
    String(s)
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;')
      .replace(/'/g,'&#39;');

  function badge(txt, type='info') {
    return `<span class="badge badge-${type}">${esc(txt)}</span>`;
  }

  function iconTag(cls) {
    return cls ? `<i class="${esc(cls)}"></i> <code class="ml-1">${esc(cls)}</code>` : '—';
  }

  function routeCell(obj) {
    const route = obj.route || null;
    const url   = obj.url || '#';
    if (!route) return '—';
    const link  = url && url !== '#' ? `<a href="${esc(url)}">${esc(route)}</a>` : `<span>${esc(route)}</span>`;
    return `<div>${link}</div>`;
  }

  function keyRow(label, valueHtml) {
    return `<tr><th class="w-35">${esc(label)}</th><td>${valueHtml}</td></tr>`;
  }

  function renderTableHTML(data) {
    // Decide which field order to use
    const isDb   = !!data.db;
    const isParent = data.type === 'parent';
    const order = isDb
      ? (isParent
          ? ['id','type','name','label','icon','route_segment','route','permission','order','created_at','updated_at']
          : ['id','type','parent_id','parent_name','name','label','icon','route','permission','order','created_at','updated_at'])
      : (isParent
          ? ['type','source','key','name','label','icon','route_segment','route','permission','plural']
          : ['type','source','parent_key','key','name','label','icon','route','permission','plural']);

    const labels = {
      id: 'DB ID',
      type: 'Type',
      source: 'Source',
      key: 'Key',
      parent_key: 'Parent Key',
      parent_id: 'Parent ID',
      parent_name: 'Parent Name',
      name: 'Name',
      label: 'Label',
      icon: 'Icon',
      route_segment: 'Route Segment',
      route: 'Route',
      url: 'URL',
      permission: 'Permission',
      plural: 'Plural',
      order: 'Order',
      created_at: 'Created',
      updated_at: 'Updated'
    };

    const rows = [];
    order.forEach(k => {
      if (!(k in data)) return; // skip if not present
      let html;
      switch(k) {
        case 'icon':        html = iconTag(data.icon); break;
        case 'route':       html = routeCell(data);    break;
        case 'permission':  html = data.permission ? badge(data.permission, 'primary') : '—'; break;
        case 'type':        html = badge(data.type, 'secondary'); break;
        case 'source':      html = data.source ? badge(data.source, 'light') : '—'; break;
        case 'plural':      html = (data.plural === true) ? badge('true','success') :
                                     (data.plural === false) ? badge('false','dark') : '—'; break;
        default:            html = data[k] !== null && data[k] !== undefined && String(data[k]).length
                                  ? `<code>${esc(data[k])}</code>` : '—';
      }
      rows.push(keyRow(labels[k] || k, html));
    });

    return `<div class="osmm-details">
      <table class="table table-sm table-striped mb-0">
        <tbody>${rows.join('')}</tbody>
      </table>
    </div>`;
  }

  function renderDetailsTable(targetId, data){
    const el = document.getElementById(targetId);
    if (!el) return;
    el.innerHTML = renderTableHTML(data);
  }

  function wireClicks(selector, targetId){
    document.addEventListener('click', function(ev){
      const node = ev.target.closest(selector);
      if(!node) return;
      ev.preventDefault();
      document.querySelectorAll(selector+'.active').forEach(n => n.classList.remove('active'));
      node.classList.add('active');
      try {
        const payload = JSON.parse(node.dataset.item || '{}');
        renderDetailsTable(targetId, payload);
      } catch(e) { /* ignore parse errors */ }
    });
  }

  // Left & right sidebars (native, merged)
  wireClicks('[data-osmm-item="native"]',  'details-native');
  wireClicks('[data-osmm-item="merged"]',  'details-merged');

  // Center overrides list
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
  .osmm-details .table td, .osmm-details .table th { vertical-align: middle; }
  .osmm-details .w-35 { width: 35%; }
  .osmm-overrides .list-group-item.active,
  .osmm-sidebar .osmm-link.active {
    background: rgba(0,123,255,.08);
    border-left: 3px solid #007bff;
  }
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
