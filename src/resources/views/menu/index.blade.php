@extends('web::layouts.app')
@section('page_title', 'OSMM Menu Manager')

@section('content')
<div class="row">
  {{-- LEFT: Native sidebar --}}
  <div class="col-md-4">
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center">
        <strong>Native Sidebar</strong>
        <small class="text-muted ml-2">package.sidebar</small>
      </div>
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
      <div id="details-native" class="card-body small">
        <em>Select an item above…</em>
      </div>
    </div>
  </div>

  {{-- CENTER: DB Overrides list + details + edit/update --}}
  <div class="col-md-4">
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center">
        <strong>Overrides in Database</strong>
        <small class="text-muted ml-2">osmm_menu_items</small>
      </div>
      <div class="card-body p-0" style="max-height:60vh; overflow:auto">
        @include('seat-osmm::menu.partials.overrides_list', ['rows' => $dbRows])
      </div>
    </div>

    <div class="card">
      <div class="card-header"><strong>Details: Override</strong></div>
      <div id="details-overrides" class="card-body small">
        <em>Select an override row…</em>
      </div>

      <div class="card-footer">
        {{-- EDIT / UPDATE FORM (pre-filled on click) --}}
        <form id="override-edit-form" method="post" action="{{ route('osmm.menu.parent.upsert') }}">
          @csrf
          {{-- hidden fields (populated by JS) --}}
          <input type="hidden" name="id" id="edit-id">
          <input type="hidden" name="parent_id" id="edit-parent_id"> {{-- for children --}}

          <div class="form-row">
            <div class="form-group col-md-6 mb-2">
              <label class="mb-0">Name</label>
              <input type="text" class="form-control form-control-sm" name="name" id="edit-name" placeholder="Display name">
              <small class="form-text text-muted">Shown in the sidebar title (parent) or child label.</small>
            </div>
            <div class="form-group col-md-6 mb-2">
              <label class="mb-0">Icon</label>
              <input type="text" class="form-control form-control-sm" name="icon" id="edit-icon" placeholder="e.g., fas fa-user">
              <small class="form-text text-muted">Font Awesome class, e.g. <code>fas fa-cog</code>.</small>
            </div>
          </div>

          <div id="wrap-route-segment" class="form-group mb-2">
            <label class="mb-0">Route Segment (parents only)</label>
            <input type="text" class="form-control form-control-sm" name="route_segment" id="edit-route_segment" placeholder="e.g., configuration">
            <small class="form-text text-muted">Must match the native top-level section you want to override.</small>
          </div>

          <div class="form-row">
            <div class="form-group col-md-7 mb-2">
              <label class="mb-0">Route (optional)</label>
              <input type="text" class="form-control form-control-sm" name="route" id="edit-route" placeholder="e.g., seatcore::tools.moons.index">
              <small class="form-text text-muted">Named route. If not registered, the menu link won’t be clickable.</small>
            </div>
            <div class="form-group col-md-5 mb-2">
              <label class="mb-0">Permission</label>
              <select class="form-control form-control-sm" name="permission" id="edit-permission">
                <option value="">(none)</option>
                @foreach(($allPermissions ?? []) as $perm)
                  <option value="{{ $perm }}">{{ $perm }}</option>
                @endforeach
              </select>
              <small class="form-text text-muted">Users must have this permission to see the item.</small>
            </div>
          </div>

          <div class="form-row">
            <div id="wrap-parent-select" class="form-group col-md-8 mb-2" style="display:none;">
              <label class="mb-0">Parent (for child items)</label>
              <select class="form-control form-control-sm" id="edit-parent-select">
                @foreach(($parentOptions ?? []) as $p)
                  <option value="{{ $p['parent_id'] ?? '' }}">{{ $p['label'] }}</option>
                @endforeach
              </select>
              <small class="form-text text-muted">Choose the top-level section this child belongs to.</small>
            </div>
            <div class="form-group col-md-4 mb-2">
              <label class="mb-0">Order</label>
              <input type="number" min="1" class="form-control form-control-sm" name="order" id="edit-order" placeholder="1">
              <small class="form-text text-muted">Position among siblings (1 = first).</small>
            </div>
          </div>

          <div class="d-flex justify-content-between align-items-center">
            <div>
              <button type="submit" class="btn btn-primary btn-sm" id="edit-submit">Save Changes</button>
              <button type="button" class="btn btn-outline-danger btn-sm" id="edit-delete">Delete</button>
            </div>
            <small id="edit-mode-help" class="text-muted"></small>
          </div>
        </form>

        {{-- Hidden delete form --}}
        <form id="override-delete-form" method="post" action="{{ route('osmm.menu.delete') }}" style="display:none;">
          @csrf
          @method('DELETE')
          <input type="hidden" name="id" id="delete-id">
        </form>
      </div>
    </div>
  </div>

  {{-- RIGHT: Merged sidebar --}}
  <div class="col-md-4">
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center">
        <strong>Merged Sidebar</strong>
        <small class="text-muted ml-2">DB overrides applied</small>
      </div>
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

{{-- CREATE FORMS --}}
<div class="row mt-4">
  <div class="col-md-6">
    <div class="card">
      <div class="card-header"><strong>Create Parent (Top-level)</strong></div>
      <div class="card-body">
        <form method="post" action="{{ route('osmm.menu.parent.upsert') }}">
          @csrf
          <div class="form-row">
            <div class="col-md-4 mb-2">
              <label class="mb-0">Name</label>
              <input name="name" class="form-control form-control-sm" placeholder="e.g., Tools">
              <small class="form-text text-muted">Shown as the section header.</small>
            </div>
            <div class="col-md-4 mb-2">
              <label class="mb-0">Icon</label>
              <input name="icon" class="form-control form-control-sm" placeholder="fas fa-wrench">
              <small class="form-text text-muted">Font Awesome icon.</small>
            </div>
            <div class="col-md-4 mb-2">
              <label class="mb-0">Route Segment</label>
              <input name="route_segment" class="form-control form-control-sm" placeholder="tools" required>
              <small class="form-text text-muted">Must match native section to override.</small>
            </div>
          </div>
          <div class="form-row">
            <div class="col-md-7 mb-2">
              <label class="mb-0">Route (optional)</label>
              <input name="route" class="form-control form-control-sm" placeholder="seatcore::tools.market.browser">
              <small class="form-text text-muted">If set, parent is clickable.</small>
            </div>
            <div class="col-md-5 mb-2">
              <label class="mb-0">Permission</label>
              <select name="permission" class="form-control form-control-sm">
                <option value="">(none)</option>
                @foreach(($allPermissions ?? []) as $perm)
                  <option value="{{ $perm }}">{{ $perm }}</option>
                @endforeach
              </select>
              <small class="form-text text-muted">Users must have this permission.</small>
            </div>
          </div>
          <div class="form-row">
            <div class="col-md-3 mb-2">
              <label class="mb-0">Order</label>
              <input name="order" type="number" class="form-control form-control-sm" placeholder="1" min="1">
              <small class="form-text text-muted">Position among top-level sections.</small>
            </div>
          </div>
          <button class="btn btn-primary btn-sm">Create Parent</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card">
      <div class="card-header"><strong>Create Child (Menu Item)</strong></div>
      <div class="card-body">
        <form method="post" action="{{ route('osmm.menu.child.upsert') }}">
          @csrf
          <div class="form-row">
            <div class="col-md-6 mb-2">
              <label class="mb-0">Parent Section</label>
              <select name="parent_id" class="form-control form-control-sm" required>
                @foreach(($parentOptions ?? []) as $p)
                  <option value="{{ $p['parent_id'] ?? '' }}">{{ $p['label'] }}</option>
                @endforeach
              </select>
              <small class="form-text text-muted">Which top-level section this child lives under.</small>
            </div>
            <div class="col-md-6 mb-2">
              <label class="mb-0">Name</label>
              <input name="name" class="form-control form-control-sm" placeholder="e.g., Moons Reporter">
              <small class="form-text text-muted">Label shown in the submenu.</small>
            </div>
          </div>
          <div class="form-row">
            <div class="col-md-7 mb-2">
              <label class="mb-0">Route</label>
              <input name="route" class="form-control form-control-sm" placeholder="seatcore::tools.moons.index" required>
              <small class="form-text text-muted">Named route the item links to.</small>
            </div>
            <div class="col-md-5 mb-2">
              <label class="mb-0">Permission</label>
              <select name="permission" class="form-control form-control-sm">
                <option value="">(none)</option>
                @foreach(($allPermissions ?? []) as $perm)
                  <option value="{{ $perm }}">{{ $perm }}</option>
                @endforeach
              </select>
              <small class="form-text text-muted">Restrict visibility to this permission.</small>
            </div>
          </div>
          <div class="form-row">
            <div class="col-md-6 mb-2">
              <label class="mb-0">Icon</label>
              <input name="icon" class="form-control form-control-sm" placeholder="fas fa-moon">
              <small class="form-text text-muted">Optional Font Awesome icon.</small>
            </div>
            <div class="col-md-3 mb-2">
              <label class="mb-0">Order</label>
              <input name="order" type="number" class="form-control form-control-sm" placeholder="1" min="1">
              <small class="form-text text-muted">Position within the parent’s submenu.</small>
            </div>
          </div>
          <button class="btn btn-primary btn-sm">Create Child</button>
        </form>
      </div>
    </div>
  </div>
</div>

{{-- Styling --}}
<style>
  .osmm-sidebar a.osmm-link { cursor: pointer; }
  .osmm-sidebar .osmm-link.active,
  .osmm-overrides .list-group-item.active {
    background: rgba(0,123,255,.08);
    border-left: 3px solid #007bff;
  }
  .osmm-details .table td, .osmm-details .table th { vertical-align: middle; }
  .osmm-details .w-35 { width: 35%; }
</style>

{{-- Script: details table + edit form population --}}
<script>
(function(){
  // ---- helpers ---------------------------------------------------
  const esc = (s) => (s === null || s === undefined) ? '' :
    String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;')
             .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');

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
    const isDb     = !!data.db;
    const isParent = data.type === 'parent';
    const order = isDb
      ? (isParent
          ? ['id','type','name','label','icon','route_segment','route','permission','order','created_at','updated_at']
          : ['id','type','parent_id','parent_name','name','label','icon','route','permission','order','created_at','updated_at'])
      : (isParent
          ? ['type','source','key','name','label','icon','route_segment','route','permission','plural']
          : ['type','source','parent_key','key','name','label','icon','route','permission','plural']);

    const labels = {
      id: 'DB ID', type: 'Type', source: 'Source', key: 'Key',
      parent_key: 'Parent Key', parent_id: 'Parent ID', parent_name: 'Parent Name',
      name: 'Name', label: 'Label', icon: 'Icon', route_segment: 'Route Segment',
      route: 'Route', url: 'URL', permission: 'Permission', plural: 'Plural',
      order: 'Order', created_at: 'Created', updated_at: 'Updated'
    };

    const rows = [];
    order.forEach(k => {
      if (!(k in data)) return;
      let html;
      switch(k) {
        case 'icon':       html = iconTag(data.icon); break;
        case 'route':      html = routeCell(data);    break;
        case 'permission': html = data.permission ? badge(data.permission, 'primary') : '—'; break;
        case 'type':       html = badge(data.type, 'secondary'); break;
        case 'source':     html = data.source ? badge(data.source, 'light') : '—'; break;
        case 'plural':     html = (data.plural === true) ? badge('true','success') :
                                   (data.plural === false) ? badge('false','dark') : '—'; break;
        default:           html = (data[k] !== null && data[k] !== undefined && String(data[k]).length)
                                  ? `<code>${esc(data[k])}</code>` : '—';
      }
      rows.push(keyRow(labels[k] || k, html));
    });

    return `<div class="osmm-details">
      <table class="table table-sm table-striped mb-0"><tbody>${rows.join('')}</tbody></table>
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
      } catch(e) {}
    });
  }

  // LEFT + RIGHT sidebars
  wireClicks('[data-osmm-item="native"]',  'details-native');
  wireClicks('[data-osmm-item="merged"]',  'details-merged');

  // CENTER overrides (also fills the edit form)
  wireClicks('[data-osmm-override-row]',   'details-overrides');

  // ---- Edit form population -------------------------------------
  const ROUTE_PARENT_UPSERT = @json(route('osmm.menu.parent.upsert'));
  const ROUTE_CHILD_UPSERT  = @json(route('osmm.menu.child.upsert'));
  const ROUTE_DELETE        = @json(route('osmm.menu.delete'));

  const form   = document.getElementById('override-edit-form');
  const delFm  = document.getElementById('override-delete-form');

  function fillSelectPermission(value){
    const sel = document.getElementById('edit-permission');
    if (!sel) return;
    if (value && ![...sel.options].some(o => o.value === value)) {
      const opt = document.createElement('option');
      opt.value = value; opt.textContent = value;
      sel.appendChild(opt);
    }
    sel.value = value || '';
  }

  function setEditMode(data){
    if (!form) return;
    const isParent = (data.type === 'parent');

    form.action = isParent ? ROUTE_PARENT_UPSERT : ROUTE_CHILD_UPSERT;

    document.getElementById('edit-id').value = data.id || '';
    document.getElementById('edit-name').value = data.name || '';
    document.getElementById('edit-icon').value = data.icon || '';
    document.getElementById('edit-route').value = data.route || '';
    document.getElementById('edit-order').value = data.order || '';
    fillSelectPermission(data.permission);

    const wrapRouteSeg = document.getElementById('wrap-route-segment');
    const routeSegInput = document.getElementById('edit-route_segment');
    const wrapParent = document.getElementById('wrap-parent-select');
    const parentIdInput = document.getElementById('edit-parent_id');
    const parentSelect  = document.getElementById('edit-parent-select');

    if (isParent) {
      wrapRouteSeg.style.display = '';
      routeSegInput.value = data.route_segment || '';
      wrapParent.style.display = 'none';
      parentIdInput.value = '';
    } else {
      wrapRouteSeg.style.display = 'none';
      routeSegInput.value = '';
      wrapParent.style.display = '';
      if (parentSelect) {
        if (data.parent_id) parentSelect.value = data.parent_id;
        parentIdInput.value = parentSelect.value || data.parent_id || '';
        parentSelect.addEventListener('change', () => {
          parentIdInput.value = parentSelect.value;
        }, { once: true });
      }
    }

    const help = document.getElementById('edit-mode-help');
    if (help) help.textContent = isParent ? 'Editing parent override' : 'Editing child override';

    const delBtn = document.getElementById('edit-delete');
    if (delBtn && delFm) {
      delBtn.onclick = function () {
        if (!data.id) return;
        if (confirm('Delete this override?')) {
          document.getElementById('delete-id').value = data.id;
          delFm.submit();
        }
      };
    }
  }

  // Also bind to center click to fill form
  document.addEventListener('click', function(ev){
    const node = ev.target.closest('[data-osmm-override-row]');
    if (!node) return;
    try {
      const payload = JSON.parse(node.dataset.item || '{}');
      setEditMode(payload);
    } catch (e) {}
  });
})();
</script>
@endsection
